<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique(); // basic|featured|premium|enterprise
            $table->string('name');
            $table->unsignedInteger('price_monthly')->default(0); // INR paise or whole rupees — use whole rupees
            $table->json('features')->nullable();
            $table->unsignedTinyInteger('ranking_boost')->default(0); // 0-100 scale later normalized
            $table->boolean('is_sponsored')->default(false);
            $table->boolean('homepage_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('plan_id');
            $table->string('status')->default('active')->index(); // active|cancelled|expired|trial
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(false);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed default plans
        $now = now();
        DB::table('plans')->insert([
            [
                'code' => 'basic',
                'name' => 'Basic',
                'price_monthly' => 0,
                'features' => json_encode([
                    'listing' => true,
                    'basic_leads' => true,
                    'limited_profile' => true,
                ]),
                'ranking_boost' => 0,
                'is_sponsored' => false,
                'homepage_featured' => false,
                'sort_order' => 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'featured',
                'name' => 'Featured',
                'price_monthly' => 1999,
                'features' => json_encode([
                    'listing' => true,
                    'enhanced_profile' => true,
                    'lead_tracking' => true,
                    'review_monitoring' => true,
                    'visibility_dashboard' => true,
                    'ranking_boost' => true,
                ]),
                'ranking_boost' => 15,
                'is_sponsored' => true,
                'homepage_featured' => false,
                'sort_order' => 2,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'price_monthly' => 4999,
                'features' => json_encode([
                    'listing' => true,
                    'top_placement' => true,
                    'sponsored_badge' => true,
                    'premium_ranking' => true,
                    'analytics' => true,
                    'homepage_featured' => true,
                ]),
                'ranking_boost' => 25,
                'is_sponsored' => true,
                'homepage_featured' => true,
                'sort_order' => 3,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly' => 14999,
                'features' => json_encode([
                    'listing' => true,
                    'multi_branch' => true,
                    'fleet_management' => true,
                    'advanced_reporting' => true,
                    'api_access' => true,
                    'homepage_featured' => true,
                    'premium_ranking' => true,
                ]),
                'ranking_boost' => 30,
                'is_sponsored' => true,
                'homepage_featured' => true,
                'sort_order' => 4,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
