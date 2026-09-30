<?php

namespace Tests\Feature;

use App\Messaging\MessageSender;
use App\Models\AppNotification;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeMessageSender;
use Tests\TestCase;

/** DIQ-913: scheduled operational reminders, each sent once. */
class OpsRemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    private User $learnerUser;

    private Learner $asha;

    private User $trainerUser;

    private Instructor $ravi;

    protected function setUp(): void
    {
        parent::setUp();
        // 10:00 in Pune (UTC+5:30).
        $this->travelTo('2026-10-05 04:30:00');
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Remind School', 'slug' => 'remind-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        $sid = $this->school->id;
        $this->trainerUser = User::factory()->create(['role' => 'instructor', 'school_id' => $sid]);
        $this->ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $sid, 'user_id' => $this->trainerUser->id, 'name' => 'Ravi', 'status' => 'active']);
        $this->learnerUser = User::factory()->create(['role' => 'learner', 'school_id' => $sid]);
        $this->asha = Learner::withoutGlobalScope('school')->create(['school_id' => $sid, 'user_id' => $this->learnerUser->id, 'name' => 'Asha', 'status' => 'active']);
    }

    private function sent(string $type, ?User $user = null): int
    {
        return AppNotification::where('type', $type)->when($user, fn ($q) => $q->where('user_id', $user->id))->count();
    }

    public function test_session_reminders_24h_and_2h_once_each_in_school_time(): void
    {
        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $this->ravi->id, 'learner_id' => $this->asha->id, 'learner_name' => 'Asha',
            'session_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled',
        ]);

        $this->artisan('driveiq:ops-reminders')->assertSuccessful(); // 23h before
        $this->artisan('driveiq:ops-reminders')->assertSuccessful();
        $this->assertSame(1, $this->sent('session.reminder', $this->learnerUser));
        $this->assertSame(1, $this->sent('session.reminder', $this->trainerUser));

        $this->travelTo('2026-10-06 02:00:00'); // 07:30 Pune, 1.5h before
        $this->artisan('driveiq:ops-reminders');
        $this->artisan('driveiq:ops-reminders');
        $this->assertSame(2, $this->sent('session.reminder', $this->learnerUser));
        $this->assertStringContainsString('under 2 hours', AppNotification::where('type', 'session.reminder')->latest('id')->first()->title);
    }

    public function test_cancelled_sessions_get_no_reminder(): void
    {
        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $this->ravi->id, 'learner_id' => $this->asha->id,
            'session_date' => '2026-10-05', 'start_time' => '11:00', 'end_time' => '12:00', 'status' => 'cancelled',
        ]);
        $this->artisan('driveiq:ops-reminders');
        $this->assertSame(0, $this->sent('session.reminder'));
    }

    public function test_licence_and_vehicle_paper_expiry_alert_once_and_again_after_renewal(): void
    {
        $this->asha->update(['license_expiry_date' => '2026-10-20']);
        $car = Vehicle::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'registration_number' => 'MH12RM0001', 'type' => 'car', 'status' => 'active']);
        $puc = VehicleDocument::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'vehicle_id' => $car->id, 'type' => 'pollution', 'expiry_date' => '2026-10-10', 'status' => 'valid']);
        VehicleDocument::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'vehicle_id' => $car->id, 'type' => 'insurance', 'expiry_date' => '2027-06-01', 'status' => 'valid']);

        $this->artisan('driveiq:ops-reminders');
        $this->artisan('driveiq:ops-reminders');
        $this->assertSame(1, $this->sent('learner.licence_expiry', $this->learnerUser));
        $this->assertSame(1, $this->sent('learner.licence_expiry', $this->owner));
        $this->assertSame(1, $this->sent('vehicle.document_expiry', $this->owner));
        $this->assertStringContainsString('PUC expiring: MH12RM0001', AppNotification::where('type', 'vehicle.document_expiry')->first()->title);

        // A renewed expiry date that is again close gets its own alert.
        $puc->update(['expiry_date' => '2026-10-15']);
        $this->artisan('driveiq:ops-reminders');
        $this->assertSame(2, $this->sent('vehicle.document_expiry', $this->owner));
    }

    public function test_missing_documents_nudge_once_after_grace_period(): void
    {
        LearnerDocument::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'learner_id' => $this->asha->id, 'type' => 'photo', 'file_path' => 'x/p.jpg', 'status' => 'uploaded']);

        $this->artisan('driveiq:ops-reminders');
        $this->assertSame(0, $this->sent('learner.documents_missing'));

        $this->travelTo(now()->addDays(4));
        $this->artisan('driveiq:ops-reminders');
        $this->artisan('driveiq:ops-reminders');
        $this->assertSame(1, $this->sent('learner.documents_missing', $this->learnerUser));
        $this->assertStringContainsString('Aadhaar', AppNotification::where('type', 'learner.documents_missing')->first()->body);
        $this->assertStringNotContainsString('Photo', AppNotification::where('type', 'learner.documents_missing')->first()->body);
    }

    public function test_uploader_is_told_when_a_document_is_reviewed(): void
    {
        $doc = LearnerDocument::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'learner_id' => $this->asha->id, 'type' => 'aadhaar', 'file_path' => 'x/a.pdf', 'status' => 'uploaded']);
        Sanctum::actingAs($this->owner->refresh());

        $this->patchJson("/api/learners/{$this->asha->id}/documents/{$doc->id}", ['status' => 'rejected', 'notes' => 'Photo is blurry'])->assertOk();

        $n = AppNotification::where('type', 'document.rejected')->where('user_id', $this->learnerUser->id)->firstOrFail();
        $this->assertStringContainsString('Photo is blurry', $n->body);
    }

    /** DIQ-1005 */
    public function test_session_reminders_also_go_on_whatsapp_to_people_who_opted_in(): void
    {
        $fake = new FakeMessageSender;
        $this->app->instance(MessageSender::class, $fake);
        SchoolSetting::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $this->school->id], ['settings' => ['notifications' => ['whatsapp' => true]]]
        );
        $this->learnerUser->forceFill(['phone' => '+919876511111', 'whatsapp_opt_in_at' => now()])->save();
        // The trainer has a number but did not opt in.
        $this->trainerUser->forceFill(['phone' => '+919876522222'])->save();

        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $this->ravi->id, 'learner_id' => $this->asha->id, 'learner_name' => 'Asha',
            'session_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled', 'pickup_location' => 'Baner gate',
        ]);

        $this->artisan('driveiq:ops-reminders');
        $this->artisan('driveiq:ops-reminders');
        $this->travelTo('2026-10-06 02:00:00');
        $this->artisan('driveiq:ops-reminders');

        $this->assertSame(['+919876511111', '+919876511111'], array_column($fake->sent, 'to'));
        $this->assertStringContainsString('Session tomorrow: driving session Tue 6 Oct, 9:00 AM with Remind School. Pickup: Baner gate.', $fake->texts()[0]);
        $this->assertStringContainsString('Session in under 2 hours', $fake->texts()[1]);

        // Opting out stops further messages.
        $this->learnerUser->forceFill(['whatsapp_opt_in_at' => null])->save();
        Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $this->ravi->id, 'learner_id' => $this->asha->id,
            'session_date' => '2026-10-06', 'start_time' => '10:30', 'end_time' => '11:30', 'status' => 'scheduled',
        ]);
        $this->artisan('driveiq:ops-reminders');
        $this->assertCount(2, $fake->sent);
    }
}
