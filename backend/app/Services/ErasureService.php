<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BSEC-13/14: DPDP erasure by anonymisation (docs/DPDP-Compliance.md, "Erasure").
 *
 * The user row is kept (foreign keys from billing, audit and training records
 * point at it) but stripped of everything that identifies the person.
 *
 * ERASED: account identity (name, e-mail, phone, password, tokens, WhatsApp
 *   opt-in), learner / trainer profile PII, uploaded identity documents (rows
 *   and files), enquiries from that person, in-app notifications, message
 *   bodies they sent, consent IP / user agent, review author name.
 * KEPT, on purpose: payments and invoices (tax law, 8 years, now attached to an
 *   anonymised learner), audit_logs (append-only security record), consent rows
 *   themselves (proof of consent, without IP/UA), training records (without the
 *   learner's identity), the request row itself.
 * REFUSED: admins, school owners (close or transfer the school first) and
 *   anyone connected to a school under legal hold.
 *
 * Idempotent: an already erased user returns status "already_erased".
 */
class ErasureService
{
    public const PLACEHOLDER_DOMAIN = 'erased.invalid';

    public function __construct(private readonly DocumentStorage $documents) {}

    public function isErased(User $user): bool
    {
        return str_ends_with((string) $user->email, '@'.self::PLACEHOLDER_DOMAIN);
    }

    /** @return array<int, string> reasons erasure is refused; empty = allowed */
    public function blockers(User $user): array
    {
        $blockers = [];
        if ($user->role === 'admin') {
            $blockers[] = 'platform_admin';
        }

        $ownsSchool = DB::table('schools')->where('user_id', $user->id)->exists()
            || DB::table('school_admins')->where('user_id', $user->id)->where('role', 'owner')->where('status', '!=', 'revoked')->exists();
        if ($ownsSchool) {
            $blockers[] = 'school_owner';
        }

        $holds = (array) config('retention.legal_hold_school_ids', []);
        if ($holds) {
            $schoolIds = collect([$user->school_id])
                ->merge(DB::table('learners')->where('user_id', $user->id)->pluck('school_id'))
                ->merge(DB::table('instructors')->where('user_id', $user->id)->pluck('school_id'))
                ->filter()->unique();
            if ($schoolIds->intersect($holds)->isNotEmpty()) {
                $blockers[] = 'legal_hold';
            }
        }

        return $blockers;
    }

    /**
     * Erasure for a verified e-mail address that has no account: only enquiries
     * made with that address exist. Same result shape as erase().
     *
     * @return array{status: string, counts: array<string,int>, blockers: array<int,string>}
     */
    public function eraseContact(string $email, ?int $requestId = null): array
    {
        $email = strtolower($email);
        $query = fn () => DB::table('inquiries')->whereRaw('lower(email) = ?', [$email]);
        $count = $query()->count();
        $query()->update([
            'name' => 'Erased', 'phone' => '0000000000', 'phone_key' => null, 'email' => null, 'message' => null,
            'review_token_hash' => null, 'whatsapp_opt_in_ip' => null, 'whatsapp_opt_in_at' => null, 'updated_at' => now(),
        ]);
        AuditLog::log('erase_contact', 'Inquiry', null, [], ['inquiries' => $count, 'request_id' => $requestId]);

        return ['status' => $count > 0 ? 'erased' : 'already_erased', 'counts' => ['inquiries' => $count], 'blockers' => []];
    }

    /**
     * @return array{status: string, counts: array<string,int>, blockers: array<int,string>}
     */
    public function erase(User $user, bool $execute = true, ?int $requestId = null): array
    {
        if ($this->isErased($user)) {
            return ['status' => 'already_erased', 'counts' => [], 'blockers' => []];
        }
        if ($blockers = $this->blockers($user)) {
            return ['status' => 'blocked', 'counts' => [], 'blockers' => $blockers];
        }

        $email = strtolower((string) $user->email);
        $id = $user->id;
        $learnerIds = DB::table('learners')->where('user_id', $id)->pluck('id')->all();
        $instructorIds = DB::table('instructors')->where('user_id', $id)->pluck('id')->all();

        $inquiries = fn () => DB::table('inquiries')->where(fn ($q) => $q->where('user_id', $id)->orWhereRaw('lower(email) = ?', [$email]));
        $learnerDocs = fn () => DB::table('learner_documents')->whereIn('learner_id', $learnerIds);
        $instructorDocs = fn () => DB::table('instructor_documents')->whereIn('instructor_id', $instructorIds);

        $counts = [
            'learners' => count($learnerIds),
            'instructors' => count($instructorIds),
            'learner_documents' => $learnerIds ? $learnerDocs()->count() : 0,
            'instructor_documents' => $instructorIds ? $instructorDocs()->count() : 0,
            'inquiries' => $inquiries()->count(),
            'notifications' => DB::table('notifications')->where('user_id', $id)->count(),
            'messages' => DB::table('messages')->where('sender_id', $id)->count(),
            'consents' => DB::table('consents')->where('user_id', $id)->count(),
            'reviews' => DB::table('reviews')->where('user_id', $id)->count(),
            'access_tokens' => DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $id)->count(),
        ];

        if (! $execute) {
            return ['status' => 'dry_run', 'counts' => $counts, 'blockers' => []];
        }

        // Files first (a failed file delete aborts before any row changes; a failed
        // transaction after file deletion leaves rows pointing at missing files, which
        // the download endpoint already treats as 404).
        $files = collect();
        if ($learnerIds) {
            $files = $files->merge($learnerDocs()->get(['school_id', 'file_path']));
        }
        if ($instructorIds) {
            $files = $files->merge($instructorDocs()->get(['school_id', 'file_path']));
        }
        foreach ($files as $f) {
            $this->documents->delete($f->file_path, (int) $f->school_id);
        }

        DB::transaction(function () use ($id, $learnerIds, $instructorIds, $inquiries) {
            $now = now();

            if ($learnerIds) {
                DB::table('learner_documents')->whereIn('learner_id', $learnerIds)->delete();
                DB::table('learners')->whereIn('id', $learnerIds)->update([
                    'name' => 'Erased learner', 'mobile' => null, 'email' => null, 'dob' => null, 'address' => null,
                    'emergency_contact' => null, 'learner_license_number' => null, 'notes' => null,
                    'whatsapp_opt_in_at' => null, 'updated_at' => $now,
                ]);
                DB::table('schedules')->whereIn('learner_id', $learnerIds)->update(['learner_name' => 'Erased learner', 'notes' => null, 'pickup_location' => null]);
            }

            if ($instructorIds) {
                DB::table('instructor_documents')->whereIn('instructor_id', $instructorIds)->delete();
                DB::table('instructors')->whereIn('id', $instructorIds)->update([
                    'name' => 'Erased trainer', 'mobile' => null, 'email' => null, 'dob' => null, 'address' => null,
                    'license_number' => null, 'photo_url' => null, 'bio' => null, 'public_visible' => false,
                    'status' => 'inactive', 'updated_at' => $now,
                ]);
            }

            $inquiries()->update([
                'name' => 'Erased', 'phone' => '0000000000', 'phone_key' => null, 'email' => null, 'message' => null,
                'review_token_hash' => null, 'whatsapp_opt_in_ip' => null, 'whatsapp_opt_in_at' => null, 'updated_at' => $now,
            ]);

            DB::table('notifications')->where('user_id', $id)->delete();
            DB::table('messages')->where('sender_id', $id)->update(['body' => '[erased]', 'updated_at' => $now]);
            DB::table('consents')->where('user_id', $id)->update(['ip_address' => null, 'user_agent' => null, 'device_id' => null]);
            DB::table('reviews')->where('user_id', $id)->update(['author_name' => 'Former user']);
            DB::table('school_admins')->where('user_id', $id)->update([
                'invite_email' => null, 'invite_token_hash' => null, 'status' => 'revoked', 'updated_at' => $now,
            ]);
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $id)->delete();

            DB::table('users')->where('id', $id)->update([
                'name' => 'Erased user',
                'email' => "erased-{$id}@".self::PLACEHOLDER_DOMAIN,
                'phone' => null,
                'password' => bcrypt(Str::random(48)),
                'remember_token' => null,
                'whatsapp_opt_in_at' => null,
                'email_verified_at' => null,
                'deactivated_at' => $now,
                'updated_at' => $now,
            ]);
        });

        // Counts only, never the identity that was erased.
        AuditLog::log('erase_user', 'User', $id, [], $counts + ['request_id' => $requestId], $user->school_id);

        return ['status' => 'erased', 'counts' => $counts, 'blockers' => []];
    }
}
