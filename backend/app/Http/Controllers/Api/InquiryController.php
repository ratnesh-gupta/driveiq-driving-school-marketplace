<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInquiryRequest;
use App\Http\Requests\Api\UpdateInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Models\AuditLog;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:pending,contacted,converted,closed'],
        ]);

        // BelongsToSchool global scope auto-filters for school users.
        $query = Inquiry::with('school')
            ->orderByDesc('created_at');

        // Admins may still filter by schoolId explicitly.
        if ($request->filled('schoolId') && $request->user()?->isAdmin()) {
            $query->where('school_id', $request->input('schoolId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(InquiryResource::collection($query->get()));
    }

    public function store(StoreInquiryRequest $request): JsonResponse
    {
        $inquiry = Inquiry::withoutGlobalScope('school')
            ->create($request->toSnakeCase());
        $inquiry->load('school');

        return response()->json(new InquiryResource($inquiry), 201);
    }

    public function update(UpdateInquiryRequest $request, int $id): JsonResponse
    {
        // Global scope ensures school users only resolve their own inquiries.
        $inquiry = Inquiry::find($id);

        if (! $inquiry) {
            return response()->json(['message' => 'Inquiry not found'], 404);
        }

        $oldValues = $inquiry->only(['status', 'message', 'channel']);
        $inquiry->fill($request->toSnakeCase());
        $inquiry->save();
        $inquiry->load('school');

        AuditLog::log('update', 'Inquiry', $inquiry->id, $oldValues, $inquiry->only(['status', 'message', 'channel']));

        return response()->json(new InquiryResource($inquiry));
    }
}
