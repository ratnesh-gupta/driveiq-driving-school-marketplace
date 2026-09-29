<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** DIQ-603: public contact form + admin inbox. */
class ContactMessageController extends Controller
{
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $data = $request->validated();

        $row = ContactMessage::create([
            // Optional auth: link the account when a signed-in user writes in.
            'user_id' => auth('sanctum')->id(),
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'id' => $row->id,
            'message' => "Thanks — we'll get back to you within 24 hours.",
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:new,read,closed'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = ContactMessage::query()
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (ContactMessage $m) => $this->serialize($m)),
            'meta' => [
                'page' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'unread' => ContactMessage::where('status', 'new')->count(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = ContactMessage::find($id);
        if (! $row) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,read,closed'],
        ]);

        $row->update([
            'status' => $data['status'],
            'handled_at' => now(),
            'handled_by' => $request->user()->id,
        ]);

        return response()->json($this->serialize($row));
    }

    private function serialize(ContactMessage $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'subject' => $m->subject,
            'message' => $m->message,
            'status' => $m->status,
            'userId' => $m->user_id,
            'handledAt' => $m->handled_at?->toISOString(),
            'createdAt' => $m->created_at?->toISOString(),
        ];
    }
}
