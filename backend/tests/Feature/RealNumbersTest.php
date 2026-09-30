<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Review;
use App\Models\Schedule;
use App\Models\School;
use App\Models\TrainingProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** DIQ-904: dashboards show real numbers. */
class RealNumbersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-15 12:00:00');
        $this->owner = User::factory()->create(['role' => 'school']);
        $this->school = School::create(['name' => 'Count School', 'slug' => 'count-school', 'user_id' => $this->owner->id]);
        $this->owner->update(['school_id' => $this->school->id]);
        Sanctum::actingAs($this->owner->refresh());
    }

    private function lead(string $createdAt): void
    {
        $lead = Inquiry::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'name' => 'Lead', 'phone' => '9000000000', 'vehicle_type' => 'car', 'status' => 'pending',
        ]);
        $lead->forceFill(['created_at' => $createdAt])->save();
    }

    public function test_stats_have_a_zero_filled_six_month_trend_and_only_published_reviews(): void
    {
        $this->lead('2026-09-02');
        $this->lead('2026-09-10');
        $this->lead('2026-06-20');
        $this->lead('2026-02-01'); // outside the window

        foreach ([[5, true], [1, false]] as [$rating, $approved]) {
            Review::withoutGlobalScope('school')->create([
                'school_id' => $this->school->id, 'author_name' => 'A', 'rating' => $rating, 'content' => 'ok', 'approved' => $approved,
            ]);
        }

        $stats = $this->getJson("/api/stats/school/{$this->school->id}")->assertOk()->json();

        $this->assertSame([
            ['month' => '2026-04', 'count' => 0],
            ['month' => '2026-05', 'count' => 0],
            ['month' => '2026-06', 'count' => 1],
            ['month' => '2026-07', 'count' => 0],
            ['month' => '2026-08', 'count' => 0],
            ['month' => '2026-09', 'count' => 2],
        ], $stats['monthlyInquiries']);
        $this->assertSame(1, $stats['totalReviews']);
        $this->assertEquals(5, $stats['avgRating']);
    }

    public function test_viewing_progress_writes_nothing(): void
    {
        $learner = Learner::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Asha', 'status' => 'active']);

        $this->getJson("/api/learners/{$learner->id}/progress")
            ->assertOk()
            ->assertJsonCount(count(TrainingProgress::SKILLS), 'skills')
            ->assertJsonPath('overallCompletion', 0);
        $this->assertSame(0, TrainingProgress::withoutGlobalScope('school')->count());

        $this->putJson("/api/learners/{$learner->id}/progress", ['skillName' => 'parking', 'percentage' => 60])
            ->assertOk()->assertJsonPath('overallCompletion', 10);
    }

    public function test_learners_trained_counts_each_learner_once(): void
    {
        $ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Ravi', 'status' => 'active']);
        $meera = Instructor::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Meera', 'status' => 'active']);
        $asha = Learner::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Asha', 'status' => 'active']);

        $this->postJson("/api/learners/{$asha->id}/assign", ['instructorId' => $ravi->id])->assertOk();
        $this->postJson("/api/learners/{$asha->id}/assign", ['instructorId' => $ravi->id])->assertOk();
        $this->patchJson("/api/learners/{$asha->id}", ['assignedInstructorId' => $meera->id])->assertOk();
        $this->postJson("/api/schools/{$this->school->id}/learners", ['name' => 'Bina', 'assignedInstructorId' => $ravi->id])->assertCreated();

        $this->assertSame(2, (int) $ravi->fresh()->total_learners_trained);
        $this->assertSame(1, (int) $meera->fresh()->total_learners_trained);
    }

    public function test_feature_flags_count_once_and_only_when_ticked(): void
    {
        $school = $this->school;
        $school->update(['rto_assistance' => false]);
        $none = $school->fresh()->profile_completeness;

        $school->update(['has_pickup' => true, 'ac_vehicle' => true]);
        $some = $school->fresh()->profile_completeness;

        // 17 fields + 1 feature item; name alone is filled at first.
        $this->assertSame((int) round(1 / 18 * 100), $none);
        $this->assertSame((int) round(2 / 18 * 100), $some);
    }

    public function test_instructor_dashboard_is_real(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'school_id' => $this->school->id]);
        $ravi = Instructor::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'user_id' => $user->id, 'name' => 'Ravi', 'status' => 'active']);
        Learner::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Asha', 'status' => 'active', 'assigned_instructor_id' => $ravi->id]);
        Learner::withoutGlobalScope('school')->create(['school_id' => $this->school->id, 'name' => 'Old', 'status' => 'completed', 'assigned_instructor_id' => $ravi->id]);

        $session = fn (string $date, string $status = 'scheduled') => Schedule::withoutGlobalScope('school')->create([
            'school_id' => $this->school->id, 'instructor_id' => $ravi->id, 'session_date' => $date,
            'start_time' => '09:00', 'end_time' => '10:00', 'status' => $status,
        ]);
        $session('2026-09-15');
        $session('2026-09-15', 'cancelled');
        $session('2026-09-18');
        $session('2026-09-30'); // beyond a week
        foreach (['present', 'present', 'absent'] as $status) {
            Attendance::withoutGlobalScope('school')->create([
                'school_id' => $this->school->id, 'schedule_id' => $session('2026-09-01', 'completed')->id,
                'instructor_id' => $ravi->id, 'status' => $status, 'marked_at' => now(),
            ]);
        }

        Sanctum::actingAs($user);
        $this->getJson('/api/instructor/me')
            ->assertOk()
            ->assertJsonPath('dashboard.todaySessions', 1)
            ->assertJsonPath('dashboard.upcomingSessions', 1)
            ->assertJsonPath('dashboard.assignedLearners', 1)
            ->assertJsonPath('dashboard.attendanceRate', 0.6667);
    }

    /** Seeding runs without model events; seeded schools must still be scored. */
    public function test_seeded_schools_have_a_real_completeness_score(): void
    {
        $this->seed();

        foreach (School::query()->get() as $school) {
            $this->assertSame($school->calculateProfileCompleteness(), (int) $school->profile_completeness, $school->slug);
        }
        $this->assertGreaterThan(50, School::where('slug', 'skyline-driving-academy')->value('profile_completeness'));
    }
}
