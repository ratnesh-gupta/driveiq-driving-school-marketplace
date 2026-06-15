<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('service_areas');
            $table->json('batch_timings')->nullable()->after('languages');
            $table->decimal('pickup_radius_km', 6, 2)->nullable()->after('batch_timings');
            $table->boolean('simulator_training')->default(false)->after('pickup_radius_km');
            $table->boolean('ac_vehicle')->default(false)->after('simulator_training');
            $table->boolean('rto_assistance')->default(true)->after('ac_vehicle');
            $table->string('established_year', 4)->nullable()->after('rto_assistance');
            $table->unsignedInteger('total_vehicles')->nullable()->after('established_year');
            $table->unsignedInteger('total_instructors')->nullable()->after('total_vehicles');
            $table->json('accepted_payments')->nullable()->after('total_instructors');
            $table->text('cancellation_policy')->nullable()->after('accepted_payments');
            $table->unsignedTinyInteger('profile_completeness')->default(0)->after('cancellation_policy');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'languages',
                'batch_timings',
                'pickup_radius_km',
                'simulator_training',
                'ac_vehicle',
                'rto_assistance',
                'established_year',
                'total_vehicles',
                'total_instructors',
                'accepted_payments',
                'cancellation_policy',
                'profile_completeness',
            ]);
        });
    }
};
