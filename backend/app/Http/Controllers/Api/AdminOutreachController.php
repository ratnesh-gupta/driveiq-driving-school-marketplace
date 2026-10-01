<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OutreachCampaign;
use App\Models\OutreachMessage;
use App\Models\Prospect;
use App\Services\OutreachService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** DIQ-1105: outreach campaigns (admin only), plus the public unsubscribe. */
class AdminOutreachController extends Controller
{
    public function __construct(private readonly OutreachService $outreach) {}

    public function overview(): JsonResponse
    {
        return response()->json([
            'dailyCap' => (int) config('outreach.daily_cap'),
            'sentToday' => $this->outreach->sentToday(),
            'withinSendingHours' => $this->outreach->withinSendingHours(),
            'sendingHours' => sprintf('%s, %02d:00–%02d:00 IST', 'Mon–Sat', config('outreach.start_hour'), config('outreach.end_hour')),
            'mailer' => config('outreach.mailer'),
            'from' => config('outreach.from.address'),
            'placeholders' => OutreachService::PLACEHOLDERS,
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(OutreachCampaign::orderByDesc('id')->get()->map(fn ($c) => $this->present($c)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $campaign = OutreachCampaign::create([...$data, 'created_by' => $request->user()->id]);
        AuditLog::log('create', 'OutreachCampaign', $campaign->id, [], ['name' => $campaign->name]);

        return response()->json($this->present($campaign), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = OutreachCampaign::findOrFail($id);
        $data = $request->validate([
            ...$this->rules(partial: true),
            'status' => ['sometimes', Rule::in(OutreachCampaign::STATUSES)],
        ]);
        if (isset($data['audience']) && $data['audience'] !== $campaign->audience && $campaign->enrollments()->exists()) {
            return response()->json(['message' => 'People are already enrolled; make a new campaign for the other audience.'], 422);
        }

        $old = $campaign->only(['status', 'name']);
        $campaign->fill($data);
        if (($data['status'] ?? null) === 'active' && ! $campaign->started_at) {
            $campaign->started_at = now();
        }
        $campaign->save();
        AuditLog::log('update', 'OutreachCampaign', $campaign->id, $old, $campaign->only(['status', 'name']));

        return response()->json($this->present($campaign));
    }

    /** Adds matching prospects; dryRun only counts them. */
    public function enroll(Request $request, int $id): JsonResponse
    {
        $campaign = OutreachCampaign::findOrFail($id);
        $data = $request->validate([
            'stages' => ['sometimes', 'array'],
            'stages.*' => [Rule::in(['new', 'contacted'])],
            'localityId' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::in(Prospect::SOURCES)],
            'dryRun' => ['sometimes', 'boolean'],
        ]);

        $count = $this->outreach->enroll($campaign, $data, (bool) ($data['dryRun'] ?? false));

        return response()->json(['count' => $count, 'campaign' => $this->present($campaign)]);
    }

    public function test(Request $request, int $id): JsonResponse
    {
        $campaign = OutreachCampaign::findOrFail($id);
        $data = $request->validate([
            'step' => ['required', 'integer', 'min:0', 'max:'.(count($campaign->steps) - 1)],
            'email' => ['nullable', 'email'],
        ]);
        $to = $data['email'] ?? $request->user()->email;
        $this->outreach->sendTest($campaign, (int) $data['step'], $to);

        return response()->json(['message' => "Test sent to {$to}."]);
    }

    public function messages(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'campaignId' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['sending', 'sent', 'failed', 'bounced'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = OutreachMessage::query()
            ->with(['campaign:id,name', 'prospect:id,name,stage'])
            ->when($filters['campaignId'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (OutreachMessage $m) => [
                'id' => $m->id,
                'campaign' => $m->campaign?->name,
                'prospect' => $m->prospect ? ['id' => $m->prospect->id, 'name' => $m->prospect->name, 'stage' => $m->prospect->stage] : null,
                'step' => $m->step + 1,
                'to' => $m->to_masked,
                'status' => $m->status,
                'error' => $m->error,
                'clickedAt' => $m->clicked_at?->toISOString(),
                'sentAt' => $m->created_at?->toISOString(),
            ]),
            'meta' => ['page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function undeliverable(Request $request, int $id): JsonResponse
    {
        $message = OutreachMessage::findOrFail($id);
        $data = $request->validate(['reason' => ['required', Rule::in(['bounced', 'complaint'])]]);
        $this->outreach->markUndeliverable($message, $data['reason']);
        AuditLog::log($data['reason'], 'OutreachMessage', $message->id);

        return response()->json(['status' => 'bounced']);
    }

    /**
     * POST /outreach/unsubscribe/{token}: the email's one-click unsubscribe
     * (RFC 8058) and the unsubscribe page. Always answers the same way, so
     * the endpoint cannot be used to test tokens.
     */
    public function unsubscribe(string $token): JsonResponse
    {
        $this->outreach->unsubscribe($token);

        return response()->json(['message' => 'You are unsubscribed. We will not email you again.']);
    }

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:120'],
            'audience' => [$req, Rule::in(Prospect::TYPES)],
            'steps' => [$req, 'array', 'min:1', 'max:'.config('outreach.max_steps')],
            'steps.*.subject' => ['required', 'string', 'max:200'],
            'steps.*.body' => ['required', 'string', 'max:5000'],
            'steps.*.delayDays' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    private function present(OutreachCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'audience' => $c->audience,
            'status' => $c->status,
            'steps' => $c->steps,
            'startedAt' => $c->started_at?->toISOString(),
            'createdAt' => $c->created_at?->toISOString(),
            'stats' => $this->outreach->stats($c),
        ];
    }
}
