<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use App\Notifications\VerifyOwnerEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** DIQ-1102: owners of school and trainer listings confirm their email before going live. */
class EmailVerificationController extends Controller
{
    /** POST /auth/email/verification-notification */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already confirmed.']);
        }
        if (! $user->isSchool()) {
            return response()->json(['message' => 'Only listing owners need to confirm their email.'], 422);
        }

        $user->notify(new VerifyOwnerEmail);

        return response()->json(['message' => 'Verification email sent.']);
    }

    /**
     * GET /auth/email/verify/{id}/{hash} (signed). Opened from the email, so
     * it redirects to the dashboard instead of answering with JSON.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $frontend = config('app.frontend_url');
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away($frontend.'/dashboard?verified=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            AuditLog::log('email_verified', 'User', $user->id, [], ['email' => $user->email], $user->school_id);

            // Re-evaluate the listing: a complete draft now goes live.
            School::where('user_id', $user->id)->get()->each->save();
        }

        return redirect()->away($frontend.'/dashboard?verified=1');
    }
}
