<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** DIQ-602: platform admin user list and account deactivation (admins only; see routes/api.php). */
class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'in:admin,school,instructor,learner'],
            'status' => ['nullable', 'string', 'in:active,deactivated'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = User::query()
            ->with('school:id,name')
            ->when($filters['search'] ?? null, function ($q, string $term) {
                $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]));
            })
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->whereNull('deactivated_at'))
            ->when(($filters['status'] ?? null) === 'deactivated', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => $this->serialize($u)),
            'meta' => [
                'page' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->id === $request->user()->id && ! $data['active']) {
            return response()->json(['message' => 'You cannot deactivate your own account'], 422);
        }

        $wasActive = $user->isActive();

        if ($wasActive !== $data['active']) {
            $user->forceFill(['deactivated_at' => $data['active'] ? null : now()])->save();

            if (! $data['active']) {
                // Sign them out everywhere; the token guard also refuses them from now on.
                $user->tokens()->delete();
            }

            AuditLog::log(
                $data['active'] ? 'reactivate' : 'deactivate',
                'User',
                $user->id,
                ['active' => $wasActive],
                ['active' => $data['active']],
                $user->school_id,
            );
        }

        return response()->json($this->serialize($user->fresh('school:id,name')));
    }

    private function serialize(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'schoolId' => $u->school_id,
            'schoolName' => $u->school?->name,
            'active' => $u->isActive(),
            'deactivatedAt' => $u->deactivated_at?->toISOString(),
            'createdAt' => $u->created_at?->toISOString(),
        ];
    }
}
