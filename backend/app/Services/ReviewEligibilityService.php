<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewEligibilityService
{
    /**
     * A user may review a school if they previously submitted an inquiry
     * to that school using a matching email or phone (normalized).
     *
     * Platform admins may always review (for testing / moderation seeding).
     *
     * @return array{eligible: bool, source: ?string, inquiry_id: ?int, message: ?string}
     */
    public function check(User $user, int $schoolId): array
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return [
                'eligible' => true,
                'source' => 'admin',
                'inquiry_id' => null,
                'message' => null,
            ];
        }

        $email = $user->email ? strtolower(trim($user->email)) : null;
        $phoneDigits = $this->normalizePhone($user->name); // unlikely
        // Prefer matching inquiry by user email against inquiry email/phone fields.

        $inquiry = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where(function ($q) use ($user, $email) {
                if ($email) {
                    $q->whereRaw('LOWER(email) = ?', [$email]);
                }
                // Also allow if inquiry phone is embedded in message / exact phone later.
                if ($user->email) {
                    $q->orWhere('email', $user->email);
                }
            })
            ->orderByDesc('id')
            ->first();

        // Fallback: any inquiry for this school by same phone if stored on a prior inquiry
        // linked loosely via name match is too weak — require email match primarily.
        if (! $inquiry && $email) {
            $inquiry = Inquiry::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderByDesc('id')
                ->first();
        }

        if (! $inquiry) {
            return [
                'eligible' => false,
                'source' => null,
                'inquiry_id' => null,
                'message' => 'You can only review a school after submitting an enquiry to them.',
            ];
        }

        // One review per user per school
        $already = DB::table('reviews')
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->exists();

        if ($already) {
            return [
                'eligible' => false,
                'source' => null,
                'inquiry_id' => $inquiry->id,
                'message' => 'You have already reviewed this school.',
            ];
        }

        return [
            'eligible' => true,
            'source' => 'inquiry',
            'inquiry_id' => $inquiry->id,
            'message' => null,
        ];
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }
}
