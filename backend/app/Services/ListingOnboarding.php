<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Gives a listing its owner (DIQ-1101/1104): on self-registration as a school
 * or independent trainer, and when someone claims a pre-built listing.
 */
class ListingOnboarding
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /** A new draft listing for a freshly registered owner. */
    public function register(User $owner, string $type, array $extra = []): School
    {
        $utm = array_filter($extra['attribution'] ?? [], fn ($v) => is_string($v) && $v !== '');
        $school = new School([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'slug' => $this->slug($owner->name),
            'email' => $owner->email,
            'women_instructor' => (bool) ($extra['women_instructor'] ?? false),
        ]);
        // Not fillable: only the platform decides a listing's type, status and
        // attribution. Set before the first save, so it is never briefly live.
        $school->forceFill([
            'listing_type' => $type,
            'listing_status' => 'draft',
            'source' => $extra['source'] ?? self::sourceFromUtm($utm),
            'utm_source' => $utm['utm_source'] ?? null,
            'utm_medium' => $utm['utm_medium'] ?? null,
            'utm_campaign' => $utm['utm_campaign'] ?? null,
        ])->save();

        $this->attachOwner($school, $owner);

        return $school->refresh();
    }

    /**
     * Makes $owner the listing's owner: owner team row, the user's school,
     * a trainer profile for independent trainers, and the feature trial.
     */
    public function attachOwner(School $school, User $owner): void
    {
        $owner->forceFill(['role' => 'school', 'school_id' => $school->id])->save();

        SchoolAdmin::updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $owner->id],
            ['role' => 'owner', 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now()],
        );

        if ($school->user_id !== $owner->id) {
            $school->forceFill(['user_id' => $owner->id])->save();
        }

        // An independent trainer is also the listing's only trainer, so the
        // public page, scheduling and the "women trainer" filter all work.
        if ($school->isTrainer()) {
            $exists = Instructor::withoutGlobalScope('school')
                ->where('school_id', $school->id)->where('user_id', $owner->id)->exists();
            if (! $exists) {
                Instructor::withoutGlobalScope('school')->create([
                    'school_id' => $school->id,
                    'user_id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'mobile' => $school->phone,
                    'gender' => $school->women_instructor ? 'female' : null,
                    'women_instructor' => (bool) $school->women_instructor,
                    'status' => 'active',
                    'public_visible' => true,
                ]);
            }
        }

        $this->subscriptions->startTrial($school->id);

        AuditLog::log('owner_attached', 'School', $school->id, [], ['user_id' => $owner->id, 'type' => $school->listing_type], $school->id);
    }

    /** Our own channels are named in the tags we put on links (DIQ-1105/1106). */
    public static function sourceFromUtm(array $utm): string
    {
        $source = mb_strtolower($utm['utm_source'] ?? '');
        $medium = mb_strtolower($utm['utm_medium'] ?? '');

        return match (true) {
            $source === 'outreach' => 'outreach',
            $source === 'google' && in_array($medium, ['cpc', 'ppc', 'paid'], true) => 'ads',
            default => 'organic',
        };
    }

    public function slug(string $name): string
    {
        return Str::slug($name ?: 'listing').'-'.Str::lower(Str::random(5));
    }
}
