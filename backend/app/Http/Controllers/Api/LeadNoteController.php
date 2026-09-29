<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\LeadNote;
use App\Models\LeadStatusHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** DIQ-706: internal notes, follow-up dates and the timeline of a lead. */
class LeadNoteController extends Controller
{
    public function timeline(Request $request, int $id): JsonResponse
    {
        [$inquiry, $deny] = $this->resolve($request, $id);
        if ($deny) {
            return $deny;
        }

        $names = fn (array $ids) => User::whereIn('id', array_filter($ids))->pluck('name', 'id');

        $history = LeadStatusHistory::where('inquiry_id', $id)->orderBy('id')->get();
        $notes = LeadNote::withoutGlobalScope('school')->where('inquiry_id', $id)->orderBy('id')->get();
        $people = $names([...$history->pluck('changed_by_id')->all(), ...$notes->pluck('user_id')->all()]);

        $events = collect([[
            'type' => 'created',
            'at' => $inquiry->created_at?->toISOString(),
            'channel' => $inquiry->channel,
        ]])
            ->concat($history->map(fn (LeadStatusHistory $h) => [
                'type' => 'status',
                'at' => $h->created_at?->toISOString(),
                'from' => $h->from_status,
                'to' => $h->to_status,
                'by' => $people[$h->changed_by_id] ?? null,
            ]))
            ->concat($notes->map(fn (LeadNote $n) => $this->serializeNote($n, $people[$n->user_id] ?? null)))
            ->sortBy('at')
            ->values();

        return response()->json([
            'inquiryId' => $inquiry->id,
            'status' => $inquiry->status,
            'nextFollowUpAt' => $inquiry->next_follow_up_at?->toISOString(),
            'events' => $events,
        ]);
    }

    /**
     * Add a note. A note is a response to the lead (DIQ-703); a follow-up date
     * moves a lead that is still new or contacted to "follow_up".
     */
    public function store(Request $request, int $id): JsonResponse
    {
        [$inquiry, $deny] = $this->resolve($request, $id);
        if ($deny) {
            return $deny;
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'followUpAt' => ['nullable', 'date', 'after:now'],
        ]);

        $note = DB::transaction(function () use ($request, $inquiry, $data) {
            $note = LeadNote::withoutGlobalScope('school')->create([
                'inquiry_id' => $inquiry->id,
                'school_id' => $inquiry->school_id,
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'follow_up_at' => $data['followUpAt'] ?? null,
            ]);

            if ($note->follow_up_at) {
                $inquiry->next_follow_up_at = $note->follow_up_at;

                if (in_array($inquiry->status, ['pending', 'contacted'], true)) {
                    LeadStatusHistory::create([
                        'inquiry_id' => $inquiry->id,
                        'from_status' => $inquiry->status,
                        'to_status' => 'follow_up',
                        'changed_by_id' => $request->user()->id,
                    ]);
                    $inquiry->status = 'follow_up';
                }
                $inquiry->save();
            }

            $inquiry->markResponded();

            return $note;
        });

        return response()->json($this->serializeNote($note, $request->user()->name), 201);
    }

    /** @return array{0: ?Inquiry, 1: ?JsonResponse} */
    private function resolve(Request $request, int $id): array
    {
        $inquiry = Inquiry::withoutGlobalScope('school')->find($id);
        if (! $inquiry) {
            return [null, response()->json(['message' => 'Inquiry not found'], 404)];
        }

        return [$inquiry, $this->access()->school($request, (int) $inquiry->school_id)];
    }

    private function serializeNote(LeadNote $n, ?string $by): array
    {
        return [
            'type' => 'note',
            'id' => $n->id,
            'at' => $n->created_at?->toISOString(),
            'body' => $n->body,
            'followUpAt' => $n->follow_up_at?->toISOString(),
            'by' => $by,
        ];
    }
}
