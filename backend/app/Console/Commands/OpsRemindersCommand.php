<?php

namespace App\Console\Commands;

use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\Schedule;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * DIQ-913: operational reminders, each sent once (in-app).
 * - Sessions: 24 hours and 2 hours before, to the learner and the trainer.
 * - Learner licence expiring within 30 days: learner and school staff.
 * - Vehicle insurance / PUC / permit expiring within 15 days: school staff.
 * - Active learners still missing required documents after 3 days: learner.
 */
class OpsRemindersCommand extends Command
{
    public const LICENCE_WARNING_DAYS = 30;

    public const VEHICLE_WARNING_DAYS = 15;

    public const DOCUMENT_GRACE_DAYS = 3;

    /** Learner documents a school needs before training (RTO paperwork). */
    public const REQUIRED_LEARNER_DOCUMENTS = ['aadhaar', 'photo'];

    private const VEHICLE_DOC_LABELS = ['insurance' => 'Insurance', 'pollution' => 'PUC', 'permit' => 'Permit', 'registration' => 'Registration'];

    protected $signature = 'driveiq:ops-reminders';

    protected $description = 'Session reminders, licence and vehicle paper expiry alerts, missing document nudges';

    /** @var array<int, string> school id => timezone */
    private array $timezones = [];

    public function handle(NotificationService $notifications): int
    {
        $counts = [
            'sessions' => $this->sessionReminders($notifications),
            'licences' => $this->licenceExpiry($notifications),
            'vehicle papers' => $this->vehiclePaperExpiry($notifications),
            'document nudges' => $this->missingDocuments($notifications),
        ];

        $this->info(collect($counts)->map(fn ($n, $k) => "{$k}: {$n}")->join(', '));

        return self::SUCCESS;
    }

    private function sessionReminders(NotificationService $notifications): int
    {
        $sent = 0;

        Schedule::withoutGlobalScope('school')
            ->with(['instructor.user'])
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->whereNull('reminded_2h_at')
            // Session dates are local; a two-day window covers any timezone.
            ->whereBetween('session_date', [now()->subDay()->toDateString(), now()->addDays(2)->toDateString()])
            ->orderBy('id')
            ->chunkById(200, function ($sessions) use (&$sent, $notifications) {
                foreach ($sessions as $s) {
                    $startsAt = Carbon::parse($s->session_date->toDateString().' '.substr((string) $s->start_time, 0, 5), $this->timezone($s->school_id));
                    $hoursLeft = now()->diffInMinutes($startsAt, false) / 60;
                    if ($hoursLeft <= 0) {
                        continue;
                    }

                    $window = match (true) {
                        $hoursLeft <= 2 => 'reminded_2h_at',
                        $hoursLeft <= 24 && $s->reminded_24h_at === null => 'reminded_24h_at',
                        default => null,
                    };
                    if (! $window) {
                        continue;
                    }

                    $when = $startsAt->format('D j M, g:i A');
                    $title = $window === 'reminded_2h_at' ? 'Session in under 2 hours' : 'Session tomorrow';
                    $data = ['scheduleId' => $s->id];

                    if ($learnerUser = $this->learnerUser($s->learner_id)) {
                        $notifications->notify($learnerUser, 'session.reminder', $title,
                            "Driving session {$when}".($s->pickup_location ? ", pickup at {$s->pickup_location}" : '').'.', $data, (int) $s->school_id);
                    }
                    if ($trainer = $s->instructor?->user) {
                        $notifications->notify($trainer, 'session.reminder', $title,
                            'Session with '.($s->learner_name ?: 'your learner')." {$when}.", $data, (int) $s->school_id);
                    }

                    // A 2-hour reminder also closes the 24-hour one.
                    $s->forceFill([$window => now()] + ($window === 'reminded_2h_at' ? ['reminded_24h_at' => $s->reminded_24h_at ?? now()] : []))->save();
                    $sent++;
                }
            });

        return $sent;
    }

    private function licenceExpiry(NotificationService $notifications): int
    {
        $sent = 0;

        Learner::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '<=', now()->addDays(self::LICENCE_WARNING_DAYS)->toDateString())
            ->where(fn ($q) => $q->whereNull('license_expiry_notified_for')->orWhereColumn('license_expiry_notified_for', '!=', 'license_expiry_date'))
            ->orderBy('id')
            ->chunkById(200, function ($learners) use (&$sent, $notifications) {
                foreach ($learners as $l) {
                    $expired = $l->license_expiry_date->lt(today());
                    $date = $l->license_expiry_date->format('j M Y');
                    $title = $expired ? 'Learner licence expired' : 'Learner licence expiring';
                    $data = ['learnerId' => $l->id];

                    $notifications->notifySchool((int) $l->school_id, 'learner.licence_expiry', $title,
                        "{$l->name}'s learner licence ".($expired ? 'expired' : 'expires')." on {$date}.", $data);
                    if ($user = $this->learnerUser($l->id)) {
                        $notifications->notify($user, 'learner.licence_expiry', $title,
                            'Your learner licence '.($expired ? 'expired' : 'expires')." on {$date}. Talk to your school about renewing it before your test.", $data, (int) $l->school_id);
                    }

                    $l->forceFill(['license_expiry_notified_for' => $l->license_expiry_date])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    private function vehiclePaperExpiry(NotificationService $notifications): int
    {
        $sent = 0;

        VehicleDocument::withoutGlobalScope('school')
            ->with(['vehicle' => fn ($q) => $q->withoutGlobalScope('school')])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(self::VEHICLE_WARNING_DAYS)->toDateString())
            ->where(fn ($q) => $q->whereNull('expiry_notified_for')->orWhereColumn('expiry_notified_for', '!=', 'expiry_date'))
            ->orderBy('id')
            ->chunkById(200, function ($docs) use (&$sent, $notifications) {
                foreach ($docs as $d) {
                    if (! $d->vehicle || $d->vehicle->status === 'retired') {
                        $d->forceFill(['expiry_notified_for' => $d->expiry_date])->save();

                        continue;
                    }
                    $expired = $d->expiry_date->lt(today());
                    $label = self::VEHICLE_DOC_LABELS[$d->type] ?? ucfirst($d->type);

                    $notifications->notifySchool((int) $d->school_id, 'vehicle.document_expiry',
                        "{$label} ".($expired ? 'expired' : 'expiring').": {$d->vehicle->registration_number}",
                        "{$label} for {$d->vehicle->registration_number} ".($expired ? 'expired' : 'expires').' on '.$d->expiry_date->format('j M Y').'. Upload the renewed copy on the Vehicles page.',
                        ['vehicleId' => $d->vehicle_id, 'documentId' => $d->id]);

                    $d->forceFill(['expiry_notified_for' => $d->expiry_date])->save();
                    $sent++;
                }
            });

        return $sent;
    }

    private function missingDocuments(NotificationService $notifications): int
    {
        $sent = 0;

        Learner::withoutGlobalScope('school')
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->whereNull('documents_nudged_at')
            ->where('created_at', '<=', now()->subDays(self::DOCUMENT_GRACE_DAYS))
            ->orderBy('id')
            ->chunkById(200, function ($learners) use (&$sent, $notifications) {
                $have = LearnerDocument::withoutGlobalScope('school')
                    ->whereIn('learner_id', $learners->pluck('id'))
                    ->whereNotNull('file_path')
                    ->whereIn('type', self::REQUIRED_LEARNER_DOCUMENTS)
                    ->get(['learner_id', 'type'])
                    ->groupBy('learner_id');

                foreach ($learners as $l) {
                    $missing = array_diff(self::REQUIRED_LEARNER_DOCUMENTS, ($have[$l->id] ?? collect())->pluck('type')->all());
                    if ($missing && ($user = User::find($l->user_id))) {
                        $labels = collect($missing)->map(fn ($t) => $t === 'aadhaar' ? 'Aadhaar' : ucfirst($t))->join(', ', ' and ');
                        $notifications->notify($user, 'learner.documents_missing', 'Documents needed',
                            "Your school still needs your {$labels}. Upload them under Documents so your licence paperwork is not delayed.",
                            ['learnerId' => $l->id], (int) $l->school_id);
                        $sent++;
                    }
                    // Checked once; staff can see what is missing in the learner sheet.
                    $l->forceFill(['documents_nudged_at' => now()])->save();
                }
            });

        return $sent;
    }

    private function learnerUser(?int $learnerId): ?User
    {
        if (! $learnerId) {
            return null;
        }
        $userId = Learner::withoutGlobalScope('school')->whereKey($learnerId)->value('user_id');

        return $userId ? User::find($userId) : null;
    }

    private function timezone(int $schoolId): string
    {
        return $this->timezones[$schoolId] ??= SchoolSetting::forSchool($schoolId)['timezone'] ?? 'Asia/Kolkata';
    }
}
