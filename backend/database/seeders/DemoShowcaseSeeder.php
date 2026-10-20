<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\DrivePackage;
use App\Models\FeaturedPlacement;
use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\LeadNote;
use App\Models\Learner;
use App\Models\Locality;
use App\Models\OutreachCampaign;
use App\Models\Prospect;
use App\Models\Review;
use App\Models\Schedule;
use App\Models\School;
use App\Models\TrainingProgress;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ProspectService;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DIQ-1202: a lived-in demo for videos, screenshots and sales meetings, on
 * top of DatabaseSeeder. More Pune listings (incl. independent trainers), a
 * busy showcase school (Skyline: leads in every stage, learners, this
 * week's sessions) and admin acquisition data. Run with `driveiq:demo`;
 * never in production. All people and businesses are fictional.
 */
class DemoShowcaseSeeder extends Seeder
{
    private const LOCALITIES = [
        ['Aundh', 'aundh', 'Leafy west-Pune neighbourhood with many families and students.'],
        ['Viman Nagar', 'viman-nagar', 'Busy east-Pune area near the airport and IT parks.'],
        ['Kharadi', 'kharadi', 'Fast-growing IT corridor with young professionals.'],
        ['Pimple Saudagar', 'pimple-saudagar', 'Residential hub in PCMC with high learner demand.'],
        ['Bavdhan', 'bavdhan', 'Quiet hillside suburb close to the Mumbai–Bangalore highway.'],
    ];

    public function run(): void
    {
        foreach (self::LOCALITIES as [$name, $slug, $desc]) {
            Locality::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $desc]);
        }
        $loc = Locality::query()->pluck('id', 'slug');

        $this->addListings($loc);
        $this->coverImages();
        $this->showcase();
        $this->plans();
        $this->acquisition($loc);

        School::query()->each(function (School $s) {
            $s->recalculateRating();
            $s->forceFill(['profile_completeness' => $s->calculateProfileCompleteness()])->saveQuietly();
        });
    }

    private function addListings($loc): void
    {
        $listings = [
            ['Aundh Smart Drive', 'aundh', 18.5590, 73.8076, 'school', false, 5200, ['car'], ['manual', 'automatic'], 'Patient trainers, dual-control cars and early-morning batches for working professionals.'],
            ['Viman Nagar Driving School', 'viman-nagar', 18.5679, 73.9143, 'school', true, 4800, ['car', 'scooter'], ['manual'], 'Car and scooter training with doorstep pickup across Viman Nagar and Kalyani Nagar.'],
            ['Kharadi Wheels', 'kharadi', 18.5515, 73.9348, 'school', false, 5500, ['car'], ['automatic'], 'Automatic-car specialists. Weekend batches built around IT shift timings.'],
            ['Pimple Saudagar Motor Training', 'pimple-saudagar', 18.5994, 73.7998, 'school', true, 3900, ['car', 'bike'], ['manual'], 'Affordable car and two-wheeler courses with RTO test preparation.'],
            ['Meena Deshpande — Independent Trainer', 'bavdhan', 18.5113, 73.7810, 'trainer', true, 3500, ['car'], ['manual', 'automatic'], 'Woman trainer with 9 years of experience. Calm, one-to-one lessons in your own car or mine.'],
            ['Rahul Shinde — Car Trainer', 'wakad', 18.5975, 73.7700, 'trainer', false, 3000, ['car'], ['manual'], 'Independent trainer for nervous beginners. Highway and night driving practice on request.'],
        ];

        foreach ($listings as $i => [$name, $locality, $lat, $lng, $type, $women, $price, $vehicles, $trans, $desc]) {
            $slug = Str::slug(explode(' — ', $name)[0]);
            $school = School::query()->firstOrNew(['slug' => $slug]);
            $school->fill([
                'name' => $name,
                'locality_id' => $loc[$locality] ?? null,
                'address' => Str::title(str_replace('-', ' ', $locality)).', Pune',
                'latitude' => $lat, 'longitude' => $lng,
                'phone' => '+91 98220 4'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'whatsapp' => '+91 98220 4'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'email' => $slug.'@example.in',
                'description' => $desc,
                'verified' => $i % 2 === 0,
                'phone_verified' => true,
                'has_pickup' => true, 'women_instructor' => $women, 'weekend_classes' => true,
                'vehicle_types' => $vehicles, 'transmission' => $trans,
                'price_from' => $price, 'price_to' => $price * 2,
                'timings' => 'Mon–Sat 7 AM – 8 PM',
                'service_areas' => [Str::title(str_replace('-', ' ', $locality))],
                'languages' => ['Marathi', 'Hindi', 'English'],
                'batch_timings' => [['slot' => 'morning', 'time' => '6am-10am', 'enabled' => true], ['slot' => 'evening', 'time' => '4pm-8pm', 'enabled' => true]],
                'rto_assistance' => true, 'established_year' => (string) (2012 + $i),
                'total_vehicles' => $type === 'trainer' ? 1 : 3 + $i, 'total_instructors' => $type === 'trainer' ? 1 : 2 + $i,
                'accepted_payments' => ['UPI', 'Cash'],
            ]);
            $school->forceFill(['listing_type' => $type, 'listing_status' => 'published', 'published_at' => now()->subDays(20 + $i)])->saveQuietly();

            if ($type === 'trainer') {
                Instructor::withoutGlobalScope('school')->updateOrCreate(
                    ['school_id' => $school->id, 'name' => explode(' — ', $name)[0]],
                    ['status' => 'active', 'women_instructor' => $women, 'public_visible' => true, 'years_experience' => $women ? 9 : 6,
                        'languages' => ['Marathi', 'Hindi', 'English'], 'bio' => $desc],
                );
            }

            DrivePackage::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $school->id, 'name' => 'Beginner 12 sessions'],
                ['description' => '12 one-hour sessions with pickup', 'price' => $price, 'sessions' => 12, 'vehicle_type' => 'car', 'transmission' => $trans[0], 'has_pickup' => true, 'active' => true],
            );
            DrivePackage::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $school->id, 'name' => 'Licence Ready 20 sessions'],
                ['description' => '20 sessions plus RTO test preparation', 'price' => (int) round($price * 1.6, -2), 'sessions' => 20, 'vehicle_type' => 'car', 'transmission' => $trans[0], 'has_pickup' => true, 'active' => true],
            );

            foreach ([['Priya', 5, 'Very patient, I passed my test on the first try.'], ['Amit', 4, 'Good teaching and always on time.'], ['Sneha', 5, 'Felt safe from the first lesson.']] as $j => [$author, $rating, $text]) {
                if ($j > 1 + $i % 2) {
                    break;
                }
                Review::withoutGlobalScope('school')->updateOrCreate(
                    ['school_id' => $school->id, 'author_name' => $author],
                    ['rating' => $rating, 'content' => $text, 'approved' => true],
                );
            }
        }
    }

    /** Local placeholder covers instead of hot-linked stock photos. */
    private function coverImages(): void
    {
        School::query()->orderBy('id')->get()->values()->each(
            fn (School $s, int $i) => $s->forceFill(['image_url' => '/demo/school-'.($i % 12 + 1).'.svg'])->saveQuietly()
        );
    }

    /** Skyline: a busy owner's dashboard for the video. */
    private function showcase(): void
    {
        $school = School::query()->where('slug', 'skyline-driving-academy')->first();
        $owner = User::query()->where('email', 'info@skylinedrive.in')->first();
        if (! $school || ! $owner) {
            return;
        }
        $owner->forceFill(['email_verified_at' => now()])->save();
        $sid = $school->id;

        $neha = Instructor::withoutGlobalScope('school')->where('school_id', $sid)->where('name', 'Neha Kulkarni')->first();
        $vikram = Instructor::withoutGlobalScope('school')->updateOrCreate(
            ['school_id' => $sid, 'name' => 'Vikram Pawar'],
            ['mobile' => '9876501112', 'status' => 'active', 'employment_type' => 'full_time', 'years_experience' => 11,
                'public_visible' => true, 'languages' => ['Marathi', 'Hindi'], 'bio' => 'Highway and automatic specialist.'],
        );
        $car1 = Vehicle::withoutGlobalScope('school')->updateOrCreate(['school_id' => $sid, 'registration_number' => 'MH12 AB 1001'],
            ['type' => 'car', 'transmission' => 'manual', 'fuel_type' => 'petrol', 'status' => 'active', 'make_model' => 'Maruti Swift', 'year' => 2022]);
        Vehicle::withoutGlobalScope('school')->updateOrCreate(['school_id' => $sid, 'registration_number' => 'MH12 AB 1002'],
            ['type' => 'car', 'transmission' => 'automatic', 'fuel_type' => 'petrol', 'status' => 'active', 'make_model' => 'Hyundai i20 AT', 'year' => 2023]);

        // Leads in every stage, spread over six months, most answered quickly.
        $leads = [
            ['Kunal Deshmukh', 'pending', 0, null, 'car', 'Morning', 'whatsapp'],
            ['Riya Shah', 'pending', 0, null, 'car', 'Evening', 'form'],
            ['Omkar Bhosale', 'contacted', 2, 25, 'car', 'Weekend', 'call'],
            ['Tanvi Joshi', 'follow_up', 5, 40, 'car', 'Morning', 'form'],
            ['Harsh Mehta', 'interested', 9, 15, 'car', 'Evening', 'whatsapp'],
            ['Pallavi Kale', 'converted', 20, 30, 'car', 'Morning', 'form'],
            ['Arjun Nair', 'converted', 40, 55, 'car', 'Weekend', 'whatsapp'],
            ['Sakshi Gupta', 'lost', 60, 90, 'car', 'Evening', 'form'],
            ['Nikhil Rane', 'converted', 85, 20, 'car', 'Morning', 'call'],
            ['Ananya Iyer', 'converted', 120, 35, 'car', 'Evening', 'form'],
            ['Yash Thakur', 'contacted', 150, 45, 'car', 'Morning', 'whatsapp'],
        ];
        foreach ($leads as $i => [$name, $status, $daysAgo, $replyMin, $vehicle, $timing, $channel]) {
            $created = now()->subDays($daysAgo)->subHours(2 + $i);
            $lead = Inquiry::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $sid, 'name' => $name],
                ['phone' => '+91 98810 0'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'vehicle_type' => $vehicle, 'area' => 'Baner',
                    'preferred_timing' => $timing, 'channel' => $channel, 'status' => $status,
                    'message' => 'Looking for a '.strtolower($timing).' batch.'],
            );
            $lead->forceFill([
                'created_at' => $created, 'updated_at' => $created,
                'first_responded_at' => $replyMin ? $created->copy()->addMinutes($replyMin) : null,
                'response_seconds' => $replyMin ? $replyMin * 60 : null,
                'lost_reason' => $status === 'lost' ? 'Chose a school closer to home' : null,
            ])->saveQuietly();
            if ($status === 'follow_up') {
                LeadNote::withoutGlobalScope('school')->updateOrCreate(['inquiry_id' => $lead->id, 'school_id' => $sid],
                    ['user_id' => $owner->id, 'body' => 'Wants to start after exams. Call back on Monday.', 'follow_up_at' => now()->addDays(2)->setTime(10, 0)]);
            }
        }
        $school->forceFill(['typical_response_minutes' => 30])->saveQuietly();

        // Learners with progress, and this week's sessions.
        $package = DrivePackage::withoutGlobalScope('school')->where('school_id', $sid)->orderBy('id')->first();
        $people = [['Asha Patil', 70], ['Pallavi Kale', 45], ['Arjun Nair', 85], ['Nikhil Rane', 30], ['Ananya Iyer', 95]];
        foreach ($people as $i => [$name, $pct]) {
            $learner = Learner::withoutGlobalScope('school')->updateOrCreate(
                ['school_id' => $sid, 'name' => $name],
                ['mobile' => '98810 1'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'status' => 'active', 'vehicle_type' => 'car',
                    'package_id' => $package?->id, 'assigned_instructor_id' => ($i % 2 ? $vikram : $neha)?->id,
                    'start_date' => now()->subDays(10 + $i * 6)->toDateString()],
            );
            foreach (TrainingProgress::SKILLS as $k => $skill) {
                TrainingProgress::withoutGlobalScope('school')->updateOrCreate(
                    ['learner_id' => $learner->id, 'skill_name' => $skill],
                    ['school_id' => $sid, 'percentage' => max(0, min(100, $pct - $k * 12))],
                );
            }
            foreach ([-3, -1, 0, 1, 2] as $offset) {
                $trainer = ($i + $offset) % 2 ? $vikram : $neha;
                $session = Schedule::withoutGlobalScope('school')->updateOrCreate(
                    ['school_id' => $sid, 'learner_id' => $learner->id, 'session_date' => now()->addDays($offset)->toDateString()],
                    ['learner_name' => $name, 'instructor_id' => $trainer?->id, 'vehicle_id' => $car1->id,
                        'start_time' => sprintf('%02d:00', 7 + $i), 'end_time' => sprintf('%02d:00', 8 + $i),
                        'pickup_location' => 'Baner Road', 'status' => $offset < 0 ? 'completed' : 'scheduled',
                        'session_summary' => $offset < 0 ? 'Worked on clutch control and parking.' : null, 'created_by' => $owner->id],
                );
                if ($offset < 0) {
                    Attendance::withoutGlobalScope('school')->updateOrCreate(['schedule_id' => $session->id],
                        ['school_id' => $sid, 'learner_id' => $learner->id, 'instructor_id' => $trainer?->id, 'status' => 'present', 'marked_at' => now()->addDays($offset)]);
                }
            }
        }
    }

    /** Paid plans so search shows Sponsored and Featured cards. */
    private function plans(): void
    {
        $subs = app(SubscriptionService::class);
        $ids = School::query()->pluck('id', 'slug');
        foreach (['skyline-driving-academy' => 'premium', 'puneauto-academy' => 'featured', 'aundh-smart-drive' => 'featured'] as $slug => $plan) {
            if (isset($ids[$slug])) {
                $subs->assign((int) $ids[$slug], $plan, 3);
            }
        }
        if (isset($ids['kharadi-wheels'])) {
            FeaturedPlacement::query()->firstOrCreate(
                ['school_id' => $ids['kharadi-wheels'], 'placement' => 'homepage'],
                ['starts_at' => now()->subDay(), 'ends_at' => now()->addDays(30), 'notes' => 'Demo campaign'],
            );
        }
    }

    /** Admin → Prospects / Outreach: a pipeline with real-looking numbers. */
    private function acquisition($loc): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $rows = [
            ['Hadapsar Highway Driving', 'school', 'hadapsar', 'new', 'csv'],
            ['Katraj Learner Point', 'school', 'kothrud', 'new', 'csv'],
            ['Shree Ganesh Motor School', 'school', 'pimpri', 'contacted', 'csv'],
            ['Wakad Drive Pro', 'school', 'wakad', 'contacted', 'outreach'],
            ['Sunita Jadhav (trainer)', 'trainer', 'aundh', 'replied', 'ads'],
            ['Kalyani Nagar Car Classes', 'school', 'viman-nagar', 'new', 'ads'],
            ['Balewadi Drivers Club', 'school', 'baner', 'lost', 'manual'],
        ];
        foreach ($rows as $i => [$name, $type, $locality, $stage, $source]) {
            $p = Prospect::query()->firstOrCreate(['name' => $name], [
                'type' => $type, 'contact_person' => ['Sunil', 'Rakesh', 'Mahesh', 'Prakash', 'Sunita', 'Anil', 'Deepak'][$i],
                'phone' => '98230 5'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'email' => Str::slug($name).'@example.in',
                'locality_id' => $loc[$locality] ?? null, 'source' => $source, 'owner_admin_id' => $admin?->id,
            ]);
            $p->forceFill(['stage' => $stage, 'last_contacted_at' => $stage === 'new' ? null : now()->subDays(3)])->save();
        }
        $first = Prospect::query()->where('name', 'Hadapsar Highway Driving')->first();
        if ($first && ! $first->school_id) {
            app(ProspectService::class)->createListing($first);
        }

        $campaign = OutreachCampaign::query()->firstOrCreate(['name' => 'Pune schools – pilot'], [
            'audience' => 'school', 'status' => 'active', 'created_by' => $admin?->id,
            'steps' => [['subject' => 'Learners in {{locality}} are looking for {{name}}', 'body' => "Hi {{contact}},\n\nYour free listing: {{link}}"]],
        ]);
        $campaign->forceFill(['started_at' => now()->subDays(6)])->save();
        Prospect::query()->whereIn('stage', ['contacted', 'replied'])->get()->each(function (Prospect $p, int $i) use ($campaign) {
            DB::table('outreach_enrollments')->insertOrIgnore([
                'campaign_id' => $campaign->id, 'prospect_id' => $p->id, 'next_step' => 1, 'status' => 'completed',
                'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(3),
            ]);
            DB::table('outreach_messages')->insertOrIgnore([
                'campaign_id' => $campaign->id, 'prospect_id' => $p->id, 'step' => 0,
                'to_masked' => mb_substr($p->email, 0, 2).'***@example.in', 'to_hash' => hash('sha256', (string) $p->email),
                'status' => 'sent', 'unsubscribe_hash' => hash('sha256', 'demo-'.$p->id),
                'clicked_at' => $i % 2 === 0 ? now()->subDays(5) : null,
                'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(6),
            ]);
        });
    }
}
