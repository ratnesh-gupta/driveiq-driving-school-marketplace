<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Platform-wide audit trail (platform admins only; see routes/api.php). */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'userId' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:50'],
            'modelType' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = AuditLog::query()
            ->when($filters['schoolId'] ?? null, fn ($q, $v) => $q->where('school_id', $v))
            ->when($filters['userId'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['modelType'] ?? null, fn ($q, $v) => $q->where('model_type', $v))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'schoolId' => $log->school_id,
                'userId' => $log->user_id,
                'action' => $log->action,
                'modelType' => $log->model_type,
                'modelId' => $log->model_id,
                'oldValues' => $log->old_values,
                'newValues' => $log->new_values,
                'ipAddress' => $log->ip_address,
                'createdAt' => $log->created_at?->toISOString(),
            ]),
            'meta' => [
                'page' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
