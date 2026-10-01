<?php

namespace App\Services;

use App\Messaging\Message;
use App\Messaging\Messenger;
use App\Models\AuditLog;
use App\Models\ListingClaim;
use App\Models\ListingClaimCode;
use App\Models\Prospect;
use App\Models\School;
use App\Models\User;
use App\Notifications\ClaimCodeNotification;
use App\Notifications\VerifyOwnerEmail;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * DIQ-1104: the owner of a pre-built listing claims it. They open a link,
 * prove they control the listing's email (or phone) with a one-time code,
 * and get an owner account; the listing then goes live like any draft.
 */
class ListingClaimService
{
    public const LINK_DAYS = 30;

    public const CODE_MINUTES = 10;

    public const CODES_PER_HOUR = 3;

    public function __construct(
        private readonly ListingOnboarding $onboarding,
        private readonly Messenger $messenger,
    ) {}

    /** A fresh claim link; the raw token is only ever in the returned URL. */
    public function issue(School $school, ?Prospect $prospect = null, ?int $outreachMessageId = null): array
    {
        $token = Str::random(48);
        $claim = ListingClaim::create([
            'school_id' => $school->id,
            'prospect_id' => $prospect?->id,
            'token_hash' => ListingClaim::hashToken($token),
            'expires_at' => now()->addDays(self::LINK_DAYS),
            'outreach_message_id' => $outreachMessageId,
        ]);

        return ['claim' => $claim, 'url' => config('app.frontend_url').'/claim/'.$token];
    }

    /** Where a code can be sent: masked email and/or phone. */
    public function channels(School $school): array
    {
        $out = [];
        if ($school->email && filter_var($school->email, FILTER_VALIDATE_EMAIL)) {
            $out['email'] = self::maskEmail($school->email);
        }
        // SMS needs a real provider in production; the null driver sends nothing.
        if (($phone = Phone::toE164($school->phone)) && (config('messaging.driver') ?? 'null') !== 'null') {
            $out['sms'] = Phone::mask($phone);
        }
        // DIQ-1107: listings built from a Google place can be proved with Google.
        if ($school->google_place_id && app(GoogleBusinessService::class)->enabled()) {
            $out['google'] = 'Google Business Profile';
        }

        return $out;
    }

    public function sendCode(ListingClaim $claim, string $channel): ListingClaimCode
    {
        $school = $claim->school;
        $channels = $this->channels($school);
        if (! isset($channels[$channel])) {
            throw ValidationException::withMessages(['channel' => ['We cannot send a code that way for this listing.']]);
        }

        $recent = $claim->codes()->where('created_at', '>', now()->subHour())->count();
        if ($recent >= self::CODES_PER_HOUR) {
            throw ValidationException::withMessages(['channel' => ['Too many codes requested. Try again in an hour.']]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $row = $claim->codes()->create([
            'channel' => $channel,
            'sent_to_masked' => $channels[$channel],
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES),
        ]);

        if ($channel === 'email') {
            Notification::route('mail', $school->email)->notifyNow(new ClaimCodeNotification($code, $school->name));
        } else {
            $this->messenger->send($school->phone, new Message('claim_code', ['code' => $code, 'name' => $school->name], 'ListingClaimCode', $row->id, $school->id, 'sms'));
        }

        return $row;
    }

    /**
     * Checks the code and creates the owner account.
     *
     * @return array{user: User, token: string, school: School}
     */
    public function complete(ListingClaim $claim, string $code, array $account): array
    {
        $gone = ['code' => ['This listing has already been claimed or the link has expired.']];
        if (! $claim->isUsable()) {
            throw ValidationException::withMessages($gone);
        }
        // Outside the main transaction, so a wrong guess still counts.
        $proof = $this->checkCode($claim, $code);

        return DB::transaction(function () use ($claim, $proof, $account, $gone) {
            $claim = ListingClaim::whereKey($claim->id)->lockForUpdate()->first();
            if (! $claim?->isUsable()) {
                throw ValidationException::withMessages($gone);
            }

            $user = User::create([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => $account['password'],
                'role' => 'school',
            ]);

            $school = $claim->school;
            $school->forceFill(['listing_status' => 'draft', 'claimed_at' => now()])->save();
            $this->onboarding->attachOwner($school, $user);

            // The code proved this address; any other one still needs confirming.
            if ($proof->channel === 'email' && mb_strtolower($user->email) === mb_strtolower((string) $school->email)) {
                $user->markEmailAsVerified();
            } else {
                $user->notify(new VerifyOwnerEmail);
            }
            $school->save(); // goes live now if nothing blocks it

            $claim->forceFill(['claimed_at' => now(), 'claimed_by' => $user->id])->save();
            if ($claim->prospect) {
                $claim->prospect->forceFill(['stage' => 'claimed'])->save();
            }
            // Other links for the same listing stop working.
            ListingClaim::where('school_id', $school->id)->whereKeyNot($claim->id)->delete();

            AuditLog::log('claimed', 'School', $school->id, [], ['user_id' => $user->id, 'proof' => $proof->channel], $school->id);

            return ['user' => $user->fresh(), 'token' => $user->createToken('api-token')->plainTextToken, 'school' => $school->fresh()];
        });
    }

    /** "Not my business / remove me": stop outreach and delete the listing we built. */
    public function decline(ListingClaim $claim): void
    {
        DB::transaction(function () use ($claim) {
            $school = $claim->school;
            if ($claim->prospect) {
                app(ProspectService::class)->markDoNotContact($claim->prospect, 'not_my_business');
            } else {
                app(OutreachSuppression::class)->suppress($school->email, $school->phone, 'not_my_business');
            }
            if ($school->exists && $school->listing_status === 'unclaimed') {
                AuditLog::log('declined', 'School', $school->id, ['name' => $school->name], [], null);
                $school->delete(); // cascades to this claim
            }
        });
    }

    /** Uses up the latest live code if it matches; every try counts against it. */
    private function checkCode(ListingClaim $claim, string $code): ListingClaimCode
    {
        $result = DB::transaction(function () use ($claim, $code) {
            $row = $claim->codes()->whereNull('used_at')->where('expires_at', '>', now())
                ->latest('id')->lockForUpdate()->first();
            if (! $row || $row->attempts >= ListingClaimCode::MAX_ATTEMPTS) {
                return 'expired';
            }
            $row->increment('attempts');
            if (! Hash::check($code, $row->code_hash)) {
                return 'wrong';
            }
            $row->forceFill(['used_at' => now()])->save();

            return $row;
        });

        return match ($result) {
            'expired' => throw ValidationException::withMessages(['code' => ['This code has expired or was tried too often. Ask for a new one.']]),
            'wrong' => throw ValidationException::withMessages(['code' => ['That code is not right.']]),
            default => $result,
        };
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }
}
