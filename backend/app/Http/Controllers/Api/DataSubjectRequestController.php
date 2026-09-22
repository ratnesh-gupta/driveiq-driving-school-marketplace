<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DataSubjectRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSubjectRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'requestType' => ['required', 'string', 'in:access,correction,deletion,other'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);

        $email = strtolower($data['email']);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        $row = DataSubjectRequest::create([
            'name' => $data['name'],
            'email' => $email,
            'request_type' => $data['requestType'],
            'details' => $data['details'] ?? null,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_id' => $user?->id,
        ]);

        AuditLog::log('data_subject_request', 'DataSubjectRequest', $row->id, [], [
            'request_type' => $row->request_type,
            'email' => $row->email,
        ]);

        return response()->json([
            'id' => $row->id,
            'status' => $row->status,
            'message' => 'Request recorded. We may contact you to verify identity.',
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $items = DataSubjectRequest::query()
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (DataSubjectRequest $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'requestType' => $r->request_type,
                'details' => $r->details,
                'status' => $r->status,
                'createdAt' => $r->created_at?->toISOString(),
            ]);

        return response()->json($items);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = DataSubjectRequest::find($id);
        if (! $row) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,in_progress,completed,rejected'],
        ]);

        $row->update([
            'status' => $data['status'],
            'handled_at' => now(),
            'handled_by' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $row->id,
            'status' => $row->status,
            'handledAt' => $row->handled_at?->toISOString(),
        ]);
    }
}
