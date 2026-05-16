<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'schoolId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $query = Inquiry::query()
            ->leftJoin('schools', 'inquiries.school_id', '=', 'schools.id')
            ->select([
                'inquiries.id',
                DB::raw('inquiries.school_id as "schoolId"'),
                DB::raw('schools.name as "schoolName"'),
                'inquiries.name',
                'inquiries.phone',
                'inquiries.email',
                DB::raw('inquiries.vehicle_type as "vehicleType"'),
                'inquiries.message',
                'inquiries.status',
                DB::raw('inquiries.created_at as "createdAt"'),
            ])
            ->orderByDesc('inquiries.created_at');

        $v = $validator->validated();
        if (isset($v['schoolId'])) $query->where('inquiries.school_id', $v['schoolId']);
        if (isset($v['status'])) $query->where('inquiries.status', $v['status']);

        return response()->json($query->get()->map(fn ($r) => $this->normalizeRow($r))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schoolId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) return response()->json(['error' => $validator->errors()->toJson()], 400);

        $v = $validator->validated();
        $inquiry = Inquiry::create([
            'school_id' => $v['schoolId'],
            'name' => $v['name'],
            'phone' => $v['phone'],
            'email' => $v['email'] ?? null,
            'vehicle_type' => $v['vehicleType'],
            'area' => $v['area'] ?? null,
            'preferred_timing' => $v['preferredTiming'] ?? null,
            'channel' => $v['channel'] ?? 'form',
            'message' => $v['message'] ?? null,
            'status' => $v['status'] ?? 'pending',
        ]);

        return response()->json($this->normalizeModel($inquiry), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $inquiry = Inquiry::query()->find($id);
        if (!$inquiry) return response()->json(['error' => 'Inquiry not found'], 404);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'vehicleType' => ['sometimes', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'preferredTiming' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'max:255'],
        ]);
        if ($validator->fails()) return response()->json(['error' => 'Invalid input'], 400);

        $map = [
            'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'vehicleType' => 'vehicle_type',
            'area' => 'area', 'preferredTiming' => 'preferred_timing', 'channel' => 'channel',
            'message' => 'message', 'status' => 'status',
        ];

        foreach ($validator->validated() as $k => $v) $inquiry->{$map[$k]} = $v;
        $inquiry->save();

        return response()->json($this->normalizeModel($inquiry));
    }

    private function normalizeRow(object $r): array
    {
        $data = (array) $r;
        $data['createdAt'] = optional($r->createdAt)->toISOString() ?? (string) $r->createdAt;
        return $data;
    }

    private function normalizeModel(Inquiry $i): array
    {
        return [
            'id' => $i->id,
            'schoolId' => $i->school_id,
            'name' => $i->name,
            'phone' => $i->phone,
            'email' => $i->email,
            'vehicleType' => $i->vehicle_type,
            'message' => $i->message,
            'status' => $i->status,
            'createdAt' => $i->created_at?->toISOString(),
        ];
    }
}
