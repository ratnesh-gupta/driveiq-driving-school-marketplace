<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** DIQ-604: consent artifacts stored server-side; the browser copy is only a cache. */
class ConsentController extends Controller
{
    /** The signed-in user's current (not withdrawn) consents. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Consent::active()
                ->where('user_id', $request->user()->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Consent $c) => $c->toApi())
        );
    }

    /**
     * Record one or more grants. Signed-in users may record any purpose; without
     * an account only the cookie banner's purpose, keyed by deviceId.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consents' => ['required', 'array', 'min:1', 'max:10'],
            'consents.*.purpose' => ['required', 'string', Rule::in(Consent::PURPOSES)],
            'consents.*.version' => ['required', 'string', 'max:32'],
            'consents.*.role' => ['nullable', 'string', 'in:school,learner,instructor,admin'],
            'deviceId' => ['nullable', 'string', 'uuid'],
        ]);

        $user = auth('sanctum')->user();

        if (! $user) {
            if (empty($data['deviceId'])) {
                throw ValidationException::withMessages(['deviceId' => ['A device id is required when not signed in.']]);
            }
            foreach ($data['consents'] as $c) {
                if (! in_array($c['purpose'], Consent::ANONYMOUS_PURPOSES, true)) {
                    return response()->json(['message' => 'Sign in to record this consent'], 401);
                }
            }
        }

        $recorded = collect($data['consents'])->map(function (array $c) use ($request, $user, $data) {
            // Portal consent is for the account's own role only.
            $role = $c['purpose'] === 'role_portal' ? $user?->role : null;

            $existing = Consent::active()
                ->where('purpose', $c['purpose'])
                ->where('version', $c['version'])
                ->when($role, fn ($q) => $q->where('role', $role))
                ->when(
                    $user,
                    fn ($q) => $q->where('user_id', $user->id),
                    fn ($q) => $q->whereNull('user_id')->where('device_id', $data['deviceId'])
                )
                ->first();

            return $existing ?? Consent::create([
                'user_id' => $user?->id,
                'device_id' => $data['deviceId'] ?? null,
                'purpose' => $c['purpose'],
                'role' => $role,
                'version' => $c['version'],
                'granted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            ]);
        });

        return response()->json($recorded->map(fn (Consent $c) => $c->toApi())->values(), 201);
    }

    /** Withdraw a purpose (all active versions). History rows are kept. */
    public function destroy(Request $request, string $purpose): Response|JsonResponse
    {
        if (! in_array($purpose, Consent::PURPOSES, true)) {
            return response()->json(['message' => 'Unknown purpose'], 404);
        }

        Consent::active()
            ->where('user_id', $request->user()->id)
            ->where('purpose', $purpose)
            ->update(['withdrawn_at' => now()]);

        return response()->noContent();
    }
}
