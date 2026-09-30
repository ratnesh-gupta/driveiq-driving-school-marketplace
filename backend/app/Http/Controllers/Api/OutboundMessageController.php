<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Platform admins: WhatsApp / SMS attempts and their outcome (DIQ-1006). */
class OutboundMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:sending,sent,failed'],
            'template' => ['nullable', 'string', 'max:64'],
            'schoolId' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = OutboundMessage::query()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['template'] ?? null, fn ($q, $v) => $q->where('template', $v))
            ->when($filters['schoolId'] ?? null, fn ($q, $v) => $q->where('school_id', $v))
            ->orderByDesc('id')
            ->paginate(50);

        $schools = School::whereIn('id', collect($page->items())->pluck('school_id')->filter()->unique())->pluck('name', 'id');

        return response()->json([
            'data' => collect($page->items())->map(fn (OutboundMessage $m) => [
                'id' => $m->id,
                'schoolName' => $schools[$m->school_id] ?? null,
                'channel' => $m->channel,
                'template' => $m->template,
                'to' => $m->to_masked,
                'relatedType' => $m->related_type,
                'relatedId' => $m->related_id,
                'status' => $m->status,
                'error' => $m->error,
                'createdAt' => $m->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'driver' => config('messaging.driver') ?? 'null',
                'last24h' => OutboundMessage::where('created_at', '>=', now()->subDay())
                    ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            ],
        ]);
    }
}
