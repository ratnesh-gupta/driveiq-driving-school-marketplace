<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $request->validated('role', 'user'),
        ]);

        $schoolId = null;

        if ($user->role === 'school') {
            $school = School::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(5)),
                'email' => $user->email,
            ]);

            $user->update(['school_id' => $school->id]);
            $user->refresh();
            $schoolId = $school->id;
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'schoolId' => $schoolId,
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

        $token = $user->createToken('api-token')->plainTextToken;

        // Prefer school_id column; fall back to owned school for legacy rows.
        $schoolId = $user->school_id ?? $user->schools()->value('id');

        return response()->json([
            'user' => $user,
            'token' => $token,
            'schoolId' => $schoolId,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $user->school_id ?? $user->schools()->value('id');

        return response()->json([
            'user' => $user,
            'schoolId' => $schoolId,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
