<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-708: public "usually replies within" badge. Platform-computed by
 * driveiq:response-badges (never school-editable); null when a school has
 * too few answered leads to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->unsignedInteger('typical_response_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropColumn('typical_response_minutes');
        });
    }
};
