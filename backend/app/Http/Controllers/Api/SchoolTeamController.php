<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * School team (DIQ-403). The owner is whoever registered the school; the owner
 * may optionally invite managers. An invite is a pending school_admins row with
 * a hashed, expiring token; no account is created or changed until the invitee
 * accepts.
 */
class SchoolTeamController extends Controller
{
    private const INVITE_TTL_DAYS = 7;

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $members = SchoolAdmin::withoutGlobalScope('school')
            ->with('user:id,name,email,role')
            ->where('school_id', $schoolId)
            ->where('status', '!=', 'revoked')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SchoolAdmin $m) => $this->serialize($m));

        return response()->json($members);
    }

    public function invite(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            // Only managers can be invited; the owner is the school's registrant.
            'role' => ['nullable', 'string', 'in:manager'],
        ]);

        $email = strtolower($data['email']);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && ! $this->canBecomeManager($user, $schoolId)) {
            return response()->json([
                'message' => 'This email already has a DriveIQ account that cannot be added as a manager.',
            ], 422);
        }

        $existing = SchoolAdmin::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($email, $user) {
                $q->where('invite_email', $email);
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
            })
            ->first();

        if ($existing && $existing->status === 'active') {
            return response()->json(['message' => 'This person is already on the team.'], 422);
        }

        $token = Str::random(48);
        $attributes = [
            'school_id' => $schoolId,
            'user_id' => $user?->id,
            'invite_email' => $email,
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->addDays(self::INVITE_TTL_DAYS),
            'role' => 'manager',
            'status' => 'pending',
            'invited_by' => $request->user()->id,
            'invited_at' => now(),
            'accepted_at' => null,
        ];

        // A pending or revoked invite is re-issued (new token) rather than duplicated.
        $member = $existing
            ? tap($existing)->update($attributes)
            : SchoolAdmin::withoutGlobalScope('school')->create($attributes);

        Notification::route('mail', $email)->notify(
            new TeamInvitation(School::findOrFail($schoolId), $token, $request->user()->name)
        );

        AuditLog::log('invite_team', 'SchoolAdmin', $member->id, [], [
            'email' => $email,
            'role' => 'manager',
        ], $schoolId);

        return response()->json($this->serialize($member->fresh('user')), 201);
    }

    /** Public: what an invite link is for, so the page can show "set password" or "sign in". */
    public function showInvitation(string $token): JsonResponse
    {
        $member = $this->pendingInvite($token);

        if (! $member) {
            return response()->json(['message' => 'This invitation is invalid or has expired.'], 404);
        }

        return response()->json([
            'schoolName' => School::whereKey($member->school_id)->value('name'),
            'email' => $member->invite_email,
            'role' => $member->role,
            'hasAccount' => User::whereRaw('LOWER(email) = ?', [$member->invite_email])->exists(),
            'expiresAt' => $member->invite_expires_at?->toISOString(),
        ]);
    }

    /**
     * Accept an invite. New people set a name and password here; people who
     * already have an account must be signed in as the invited email.
     */
    public function accept(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $member = $this->pendingInvite($request->input('token'));

        if (! $member) {
            return response()->json(['message' => 'This invitation is invalid or has expired.'], 404);
        }

        $email = $member->invite_email;
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $issueToken = false;

        if ($existing) {
            $signedIn = $request->user('sanctum');
            if (! $signedIn || $signedIn->id !== $existing->id) {
                return response()->json([
                    'message' => 'Sign in as '.$email.' to accept this invitation.',
                ], 403);
            }
            if (! $this->canBecomeManager($existing, (int) $member->school_id)) {
                return response()->json([
                    'message' => 'This account cannot be added as a manager.',
                ], 422);
            }
            $user = $existing;
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            ]);
            $user = new User([
                'name' => $data['name'],
                'email' => $email,
                'password' => $data['password'],
                'role' => 'school',
            ]);
            $issueToken = true;
        }

        DB::transaction(function () use ($user, $member): void {
            // Following the invite link proves the person controls the email.
            $user->forceFill([
                'role' => 'school',
                'school_id' => $member->school_id,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $member->update([
                'user_id' => $user->id,
                'status' => 'active',
                'accepted_at' => now(),
                'invite_token_hash' => null,
                'invite_expires_at' => null,
            ]);
        });

        AuditLog::log('accept_team_invite', 'SchoolAdmin', $member->id, ['status' => 'pending'], [
            'status' => 'active',
            'user_id' => $user->id,
        ], (int) $member->school_id);

        return response()->json([
            'user' => $user->fresh(),
            'token' => $issueToken ? $user->createToken('api-token')->plainTextToken : null,
            'schoolId' => (int) $member->school_id,
            'schoolRole' => $member->role,
        ]);
    }

    public function remove(Request $request, int $schoolId, int $memberId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $member = SchoolAdmin::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('id', $memberId)
            ->first();

        if (! $member) {
            return response()->json(['message' => 'Team member not found'], 404);
        }

        if ($member->role === 'owner') {
            return response()->json(['message' => 'The school owner cannot be removed'], 422);
        }

        $previousStatus = $member->status;
        $member->update([
            'status' => 'revoked',
            'invite_token_hash' => null,
            'invite_expires_at' => null,
        ]);

        // A removed manager loses school access immediately: sign them out, and
        // without another active membership they become a plain learner account.
        if ($member->user_id) {
            $user = User::find($member->user_id);
            $stillActive = SchoolAdmin::withoutGlobalScope('school')
                ->where('user_id', $member->user_id)
                ->where('status', 'active')
                ->exists();

            if ($user && ! $stillActive && (int) $user->school_id === $schoolId) {
                $user->forceFill(['school_id' => null, 'role' => 'learner'])->save();
            }
            $user?->tokens()->delete();
        }

        AuditLog::log('remove_team', 'SchoolAdmin', $member->id, ['status' => $previousStatus], ['status' => 'revoked'], $schoolId);

        return response()->json(null, 204);
    }

    /** Learners, instructors, admins and other schools' staff cannot be turned into managers. */
    private function canBecomeManager(User $user, int $schoolId): bool
    {
        if ($user->isAdmin() || $user->isInstructor()) {
            return false;
        }

        if ($user->isLearner()) {
            // Only an unaffiliated account (e.g. a removed former manager) may be re-invited.
            return $user->school_id === null
                && SchoolAdmin::withoutGlobalScope('school')->where('user_id', $user->id)->exists();
        }

        return $user->school_id === null || (int) $user->school_id === $schoolId;
    }

    private function pendingInvite(string $token): ?SchoolAdmin
    {
        return SchoolAdmin::withoutGlobalScope('school')
            ->where('invite_token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->where('invite_expires_at', '>', now())
            ->first();
    }

    private function serialize(SchoolAdmin $m): array
    {
        return [
            'id' => $m->id,
            'userId' => $m->user_id,
            'name' => $m->user?->name,
            'email' => $m->user?->email ?? $m->invite_email,
            'role' => $m->role,
            'status' => $m->status,
            'invitedAt' => $m->invited_at?->toISOString(),
            'acceptedAt' => $m->accepted_at?->toISOString(),
            'inviteExpiresAt' => $m->invite_expires_at?->toISOString(),
        ];
    }
}
