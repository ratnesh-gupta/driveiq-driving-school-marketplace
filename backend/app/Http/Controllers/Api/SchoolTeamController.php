<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SchoolTeamController extends Controller
{
    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchoolAccess($request, $schoolId)) {
            return $deny;
        }

        $members = SchoolAdmin::withoutGlobalScope('school')
            ->with('user:id,name,email,role')
            ->where('school_id', $schoolId)
            ->where('status', '!=', 'revoked')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SchoolAdmin $m) => [
                'id' => $m->id,
                'userId' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role,
                'status' => $m->status,
                'invitedAt' => $m->invited_at?->toISOString(),
                'acceptedAt' => $m->accepted_at?->toISOString(),
            ]);

        return response()->json($members);
    }

    public function invite(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchoolAccess($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:owner,manager'],
        ]);

        $email = strtolower($data['email']);
        $role = $data['role'] ?? 'manager';

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $user = User::create([
                'name' => $data['name'] ?? Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'role' => 'school',
                'school_id' => $schoolId,
            ]);
        } else {
            if ($user->school_id && (int) $user->school_id !== $schoolId && ! $user->isAdmin()) {
                return response()->json(['message' => 'User already belongs to another school'], 422);
            }
            $user->update([
                'role' => $user->isAdmin() ? $user->role : 'school',
                'school_id' => $schoolId,
            ]);
        }

        $existing = SchoolAdmin::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status !== 'revoked') {
            return response()->json(['message' => 'User is already on the team'], 422);
        }

        if ($existing) {
            $existing->update([
                'role' => $role,
                'status' => 'active',
                'invited_by' => $request->user()->id,
                'invited_at' => now(),
                'accepted_at' => now(),
            ]);
            $member = $existing->fresh();
        } else {
            $member = SchoolAdmin::withoutGlobalScope('school')->create([
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'role' => $role,
                'invited_by' => $request->user()->id,
                'invited_at' => now(),
                'accepted_at' => now(),
                'status' => 'active',
            ]);
        }

        AuditLog::log('invite_team', 'SchoolAdmin', $member->id, [], [
            'school_id' => $schoolId,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        return response()->json([
            'id' => $member->id,
            'userId' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $member->role,
            'status' => $member->status,
        ], 201);
    }

    public function remove(Request $request, int $schoolId, int $memberId): JsonResponse
    {
        if ($deny = $this->authorizeSchoolAccess($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $member = SchoolAdmin::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('id', $memberId)
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Team member not found'], 404);
        }

        if ($member->role === 'owner' && $member->user_id === $request->user()->id) {
            return response()->json(['message' => 'Cannot remove yourself as owner'], 422);
        }

        $member->update(['status' => 'revoked']);

        // Clear school_id if no other active membership
        $stillActive = SchoolAdmin::withoutGlobalScope('school')
            ->where('user_id', $member->user_id)
            ->where('status', 'active')
            ->exists();

        if (! $stillActive) {
            User::where('id', $member->user_id)->where('school_id', $schoolId)->update(['school_id' => null]);
        }

        AuditLog::log('remove_team', 'SchoolAdmin', $member->id, ['status' => 'active'], ['status' => 'revoked']);

        return response()->json(null, 204);
    }

    private function authorizeSchoolAccess(Request $request, int $schoolId, bool $ownerOnly = false): ?JsonResponse
    {
        $user = $request->user();

        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        if ($user->isAdmin()) {
            return null;
        }

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($ownerOnly) {
            $isOwner = SchoolAdmin::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->where('status', 'active')
                ->exists();

            // Fallback: school.user_id is the legacy owner
            $legacyOwner = School::where('id', $schoolId)->where('user_id', $user->id)->exists();

            if (! $isOwner && ! $legacyOwner) {
                return response()->json(['message' => 'Only school owners can manage the team'], 403);
            }
        }

        return null;
    }
}
