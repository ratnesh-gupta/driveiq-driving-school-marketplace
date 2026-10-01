<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Notifications\VerifyOwnerEmail;
use App\Services\ListingOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->validated('role'),
        ]);

        $schoolId = null;

        // The registrant owns a draft listing: a driving school, or an
        // independent trainer (DIQ-1101). It goes live once published.
        if ($user->role === 'school') {
            $schoolId = app(ListingOnboarding::class)->register($user, $request->validated('listingType') ?? 'school', [
                'women_instructor' => $request->boolean('womenInstructor'),
                'attribution' => $request->validated('attribution') ?? [],
            ])->id;
            $user->refresh();
            $user->notify(new VerifyOwnerEmail);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'schoolId' => $schoolId,
            'schoolRole' => $schoolId ? 'owner' : null,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Contact support if you think this is a mistake.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        // Prefer school_id column; fall back to owned school for legacy rows.
        $schoolId = $user->school_id ?? $user->schools()->value('id');

        return response()->json([
            'user' => $user,
            'token' => $token,
            'schoolId' => $schoolId,
            'schoolRole' => $this->schoolRole($user, $schoolId),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? $user->schools()->value('id');

        return response()->json([
            'user' => $user,
            'schoolId' => $schoolId,
            'schoolRole' => $this->schoolRole($user, $schoolId),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink($request->only('email'));

        // Same answer whether or not the account exists (no user enumeration).
        return response()->json([
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                // Sign out every existing session/token.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'This reset link is invalid or has expired.'], 422);
        }

        return response()->json(['message' => 'Password has been reset.']);
    }

    /** 'owner' | 'manager' for school staff, otherwise null. */
    private function schoolRole(User $user, ?int $schoolId): ?string
    {
        if (! $user->isSchool() || ! $schoolId) {
            return null;
        }

        return $this->access()->isOwner($user, (int) $schoolId) ? 'owner' : 'manager';
    }
}
