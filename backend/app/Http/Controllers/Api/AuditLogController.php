<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        return response()->json(self::present($page, withIp: true));
    }

    /**
     * One page of audit entries with the actor's and school's names, plus the
     * model types present so the UI can offer them as filters.
     */
    public static function present(LengthAwarePaginator $page, bool $withIp = false): array
    {
        $items = collect($page->items());
        $users = User::whereIn('id', $items->pluck('user_id')->filter()->unique())->get(['id', 'name', 'role'])->keyBy('id');
        $schools = School::whereIn('id', $items->pluck('school_id')->filter()->unique())->pluck('name', 'id');

        return [
            'data' => $items->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'schoolId' => $log->school_id,
                'schoolName' => $schools[$log->school_id] ?? null,
                'userId' => $log->user_id,
                'userName' => $users[$log->user_id]->name ?? null,
                'userRole' => $users[$log->user_id]->role ?? null,
                'action' => $log->action,
                'modelType' => $log->model_type,
                'modelId' => $log->model_id,
                'oldValues' => $log->old_values,
                'newValues' => $log->new_values,
                // IPs are personal data; only platform admins see them.
                'ipAddress' => $withIp ? $log->ip_address : null,
                'createdAt' => $log->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
