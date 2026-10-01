<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Prospect;
use App\Services\OutreachSuppression;
use App\Services\ProspectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** DIQ-1103: admin CRM for schools and trainers being brought on board. Admin routes only. */
class AdminProspectController extends Controller
{
    public function __construct(private readonly ProspectService $prospects) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', Rule::in(Prospect::STAGES)],
            'type' => ['nullable', Rule::in(Prospect::TYPES)],
            'source' => ['nullable', Rule::in(Prospect::SOURCES)],
            'localityId' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = Prospect::query()
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(function ($w) use ($like, $digits) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(contact_person, \'\')) LIKE ?', [$like])
                        ->orWhere('email_normalized', 'like', $like);
                    if (strlen($digits) >= 4) {
                        $w->orWhere('phone_e164', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['localityId'] ?? null, fn ($q, $v) => $q->where('locality_id', $v));

        $counts = (clone $base)->groupBy('stage')->select('stage', DB::raw('COUNT(*) AS n'))->pluck('n', 'stage');

        $page = (clone $base)
            ->when($filters['stage'] ?? null, fn ($q, $v) => $q->where('stage', $v))
            ->with(['locality:id,name', 'school:id,slug,listing_status', 'ownerAdmin:id,name'])
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Prospect $p) => $this->present($p)),
            'meta' => [
                'page' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'stageCounts' => collect(Prospect::STAGES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->input($request, ProspectService::rules());

        if ($dup = Prospect::findDuplicate($data['phone'] ?? null, $data['email'] ?? null, $data['google_place_id'] ?? null)) {
            return $this->duplicate($dup);
        }

        $prospect = Prospect::create([...$data, 'source' => 'manual', 'owner_admin_id' => $request->user()->id]);
        AuditLog::log('create', 'Prospect', $prospect->id, [], ['name' => $prospect->name]);

        return response()->json($this->present($prospect->load('locality:id,name')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);
        $data = $this->input($request, [
            ...ProspectService::rules(partial: true),
            'stage' => ['sometimes', Rule::in(array_diff(Prospect::STAGES, ['claimed']))],
            'owner_admin_id' => ['sometimes', 'nullable', Rule::exists('users', 'id')->where('role', 'admin')],
        ]);

        if (array_intersect_key($data, array_flip(['phone', 'email', 'google_place_id']))
            && ($dup = Prospect::findDuplicate($data['phone'] ?? $prospect->phone, $data['email'] ?? $prospect->email, $data['google_place_id'] ?? $prospect->google_place_id, $prospect->id))) {
            return $this->duplicate($dup);
        }
        if ($prospect->stage === 'claimed' && isset($data['stage'])) {
            return response()->json(['message' => 'This prospect has claimed their listing.'], 422);
        }

        if (($data['stage'] ?? null) === 'do_not_contact' && $prospect->stage !== 'do_not_contact') {
            unset($data['stage']);
            $prospect->fill($data)->save();
            $this->prospects->markDoNotContact($prospect, 'admin');
        } else {
            $old = $prospect->only(array_keys($data));
            $prospect->fill($data)->save();
            AuditLog::log('update', 'Prospect', $prospect->id, $old, $data);
        }

        return response()->json($this->present($prospect->fresh(['locality:id,name', 'school:id,slug,listing_status', 'ownerAdmin:id,name'])));
    }

    /**
     * POST /admin/prospects/import (multipart: file, type, preview).
     * With preview=1 nothing is saved: the admin sees the column mapping and
     * which rows are new, duplicates or invalid, then imports.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'type' => ['required', Rule::in(Prospect::TYPES)],
            'preview' => ['sometimes', 'boolean'],
        ]);

        $parsed = $this->prospects->parseCsv($request->file('file')->getRealPath(), $request->input('type'));
        if (isset($parsed['error'])) {
            return response()->json(['message' => $parsed['error'], 'mapping' => $parsed['mapping']], 422);
        }

        $summary = collect($parsed['rows'])->countBy('status')->all() + ['new' => 0, 'duplicate' => 0, 'repeated' => 0, 'invalid' => 0];

        if ($request->boolean('preview')) {
            return response()->json(['mapping' => $parsed['mapping'], 'summary' => $summary, 'rows' => $parsed['rows']]);
        }

        return response()->json([...$this->prospects->import($parsed['rows'], $request->user()->id), 'summary' => $summary], 201);
    }

    /** POST /admin/prospects/{id}/listing: build the unclaimed listing the prospect can claim. */
    public function createListing(int $id): JsonResponse
    {
        $prospect = Prospect::findOrFail($id);

        if ($prospect->school_id) {
            return response()->json(['message' => 'This prospect already has a listing.'], 422);
        }
        if (! $prospect->isContactable()) {
            return response()->json(['message' => 'This prospect is closed or asked not to be contacted.'], 422);
        }
        if ($prospect->google_place_id && DB::table('schools')->where('google_place_id', $prospect->google_place_id)->exists()) {
            return response()->json(['message' => 'A listing for this Google place already exists.'], 422);
        }

        $this->prospects->createListing($prospect);

        return response()->json($this->present($prospect->fresh(['locality:id,name', 'school:id,slug,listing_status', 'ownerAdmin:id,name'])), 201);
    }

    public function present(Prospect $p): array
    {
        return [
            'id' => $p->id,
            'type' => $p->type,
            'name' => $p->name,
            'contactPerson' => $p->contact_person,
            'phone' => $p->phone,
            'email' => $p->email,
            'website' => $p->website,
            'localityId' => $p->locality_id,
            'localityName' => $p->locality?->name,
            'address' => $p->address,
            'latitude' => $p->latitude,
            'longitude' => $p->longitude,
            'googlePlaceId' => $p->google_place_id,
            'notes' => $p->notes,
            'source' => $p->source,
            'stage' => $p->stage,
            'contactable' => $p->isContactable() && ! app(OutreachSuppression::class)->isSuppressed($p->email, $p->phone),
            'listing' => $p->school ? ['id' => $p->school->id, 'slug' => $p->school->slug, 'status' => $p->school->listing_status] : null,
            'ownerAdmin' => $p->ownerAdmin ? ['id' => $p->ownerAdmin->id, 'name' => $p->ownerAdmin->name] : null,
            'lastContactedAt' => $p->last_contacted_at?->toISOString(),
            'createdAt' => $p->created_at?->toISOString(),
            'updatedAt' => $p->updated_at?->toISOString(),
        ];
    }

    /** camelCase request => snake_case validated data. */
    private function input(Request $request, array $rules): array
    {
        $map = [
            'contactPerson' => 'contact_person', 'localityId' => 'locality_id',
            'googlePlaceId' => 'google_place_id', 'ownerAdminId' => 'owner_admin_id',
        ];
        $snake = [];
        foreach ($request->all() as $k => $v) {
            $snake[$map[$k] ?? $k] = $v;
        }
        $request->replace($snake);

        return $request->validate($rules);
    }

    private function duplicate(Prospect $dup): JsonResponse
    {
        return response()->json([
            'message' => "Already on the list as \"{$dup->name}\".",
            'errors' => ['duplicate' => ["Already on the list as \"{$dup->name}\"."]],
            'duplicateOf' => ['id' => $dup->id, 'name' => $dup->name],
        ], 422);
    }
}
