<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Learner;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * DIQ-1002: a signed-in person's own WhatsApp number and opt-in. Turning it
 * on or off is recorded as a DPDP consent (purpose whatsapp_updates), and a
 * learner's choice also applies to their learner record.
 */
class WhatsAppPreferenceController extends Controller
{
    public const CONSENT_VERSION = '2026-10';

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->present($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20'],
            'optIn' => ['required', 'boolean'],
        ]);

        $phone = array_key_exists('phone', $data) ? $data['phone'] : $user->phone;
        $e164 = Phone::toE164($phone);
        if ($phone !== null && $phone !== '' && $e164 === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number.']);
        }
        if ($data['optIn'] && ! $e164) {
            throw ValidationException::withMessages(['phone' => 'Add your WhatsApp number to turn on updates.']);
        }

        $was = $user->whatsapp_opt_in_at !== null;
        $user->forceFill([
            'phone' => $e164,
            'whatsapp_opt_in_at' => $data['optIn'] ? ($user->whatsapp_opt_in_at ?? now()) : null,
        ])->save();

        if ($data['optIn'] && ! $was) {
            Consent::create([
                'user_id' => $user->id,
                'purpose' => 'whatsapp_updates',
                'version' => self::CONSENT_VERSION,
                'granted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        } elseif (! $data['optIn'] && $was) {
            Consent::active()->where('user_id', $user->id)->where('purpose', 'whatsapp_updates')->update(['withdrawn_at' => now()]);
        }

        if ($user->isLearner()) {
            Learner::withoutGlobalScope('school')->where('user_id', $user->id)
                ->update(['whatsapp_opt_in_at' => $user->whatsapp_opt_in_at]);
        }

        if ($was !== $data['optIn']) {
            AuditLog::log($data['optIn'] ? 'whatsapp_opt_in' : 'whatsapp_opt_out', 'User', $user->id, [], [], $user->school_id);
        }

        return response()->json($this->present($user->fresh()));
    }

    private function present($user): array
    {
        return [
            'phone' => $user->phone,
            'optedIn' => $user->whatsapp_opt_in_at !== null,
            'optedInAt' => $user->whatsapp_opt_in_at?->toISOString(),
        ];
    }
}
