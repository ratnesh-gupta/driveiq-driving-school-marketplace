<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Locality;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchoolOwner(string $slug): array
    {
        $locality = Locality::first() ?? Locality::create([
            'name' => 'Notify Locality',
            'slug' => 'notify-locality-'.uniqid(),
        ]);

        $owner = User::factory()->create(['role' => 'school']);

        $school = School::create([
            'user_id' => $owner->id,
            'name' => 'Notify School '.$slug,
            'slug' => $slug,
            'locality_id' => $locality->id,
            'address' => 'Test',
            'phone' => '9000000000',
        ]);

        $owner->update(['school_id' => $school->id]);
        $owner->refresh();

        return [$owner, $school];
    }

    public function test_inquiry_create_notifies_school_owner(): void
    {
        [$owner, $school] = $this->makeSchoolOwner('notify-school-one');

        $this->postJson('/api/inquiries', [
            'schoolId' => $school->id,
            'name' => 'Lead',
            'phone' => '9888888888',
            'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'school_id' => $school->id,
            'type' => 'inquiry.created',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonFragment(['type' => 'inquiry.created']);
    }

    /** DIQ-701: lead details go to the owner and managers only, once each. */
    public function test_new_lead_notifies_only_active_staff_once(): void
    {
        [$owner, $school] = $this->makeSchoolOwner('notify-staff-only');
        $manager = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);
        $formerManager = User::factory()->create(['role' => 'school', 'school_id' => $school->id, 'deactivated_at' => now()]);
        $learner = User::factory()->create(['role' => 'learner', 'school_id' => $school->id]);
        $instructor = User::factory()->create(['role' => 'instructor', 'school_id' => $school->id]);
        $otherSchoolStaff = User::factory()->create(['role' => 'school']);

        $this->postJson('/api/inquiries', [
            'schoolId' => $school->id,
            'name' => 'Lead',
            'phone' => '9888888777',
            'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated();

        $recipients = AppNotification::where('type', 'inquiry.created')->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$owner->id, $manager->id], $recipients);
        foreach ([$formerManager, $learner, $instructor, $otherSchoolStaff] as $u) {
            $this->assertNotContains($u->id, $recipients);
        }
    }

    public function test_legacy_owner_linked_only_by_school_user_id_is_notified(): void
    {
        [$owner, $school] = $this->makeSchoolOwner('notify-legacy-owner');
        $owner->update(['school_id' => null]);

        $this->postJson('/api/inquiries', [
            'schoolId' => $school->id,
            'name' => 'Lead',
            'phone' => '9888888666',
            'vehicleType' => 'car',
            'formStartedAt' => now()->subSeconds(10)->getTimestampMs(),
        ])->assertCreated();

        $this->assertSame([$owner->id], AppNotification::where('type', 'inquiry.created')->pluck('user_id')->all());
    }

    public function test_user_cannot_see_other_users_notifications(): void
    {
        [$owner1, $school1] = $this->makeSchoolOwner('notify-school-a');
        [$owner2] = $this->makeSchoolOwner('notify-school-b');

        AppNotification::create([
            'user_id' => $owner1->id,
            'school_id' => $school1->id,
            'type' => 'inquiry.created',
            'title' => 'Private',
            'body' => 'Only owner1',
        ]);

        Sanctum::actingAs($owner2);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_mark_read_and_mark_all_read(): void
    {
        [$owner, $school] = $this->makeSchoolOwner('notify-school-read');

        $n1 = AppNotification::create([
            'user_id' => $owner->id,
            'school_id' => $school->id,
            'type' => 'inquiry.created',
            'title' => 'One',
        ]);

        $n2 = AppNotification::create([
            'user_id' => $owner->id,
            'school_id' => $school->id,
            'type' => 'inquiry.created',
            'title' => 'Two',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/notifications/'.$n1->id.'/read')
            ->assertOk()
            ->assertJsonPath('readAt', fn ($v) => $v !== null);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $this->assertNotNull($n2->fresh()->read_at);

        $this->getJson('/api/notifications?unreadOnly=1')
            ->assertOk()
            ->assertJsonPath('unreadCount', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_cannot_mark_other_users_notification_read(): void
    {
        [$owner1, $school1] = $this->makeSchoolOwner('notify-school-iso');
        [$owner2] = $this->makeSchoolOwner('notify-school-iso-2');

        $n = AppNotification::create([
            'user_id' => $owner1->id,
            'school_id' => $school1->id,
            'type' => 'inquiry.created',
            'title' => 'Secret',
        ]);

        Sanctum::actingAs($owner2);

        $this->postJson('/api/notifications/'.$n->id.'/read')
            ->assertNotFound();
    }
}
