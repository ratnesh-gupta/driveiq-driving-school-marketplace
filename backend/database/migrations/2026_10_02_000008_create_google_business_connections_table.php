<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1107: a listing owner's connected Google Business Profile. Tokens are
 * encrypted at rest (model casts) and deleted when the owner disconnects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_business_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            // The location the owner imported, e.g. accounts/1/locations/2.
            $table->string('location_name')->nullable();
            $table->string('location_title')->nullable();
            $table->string('place_id')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_business_connections');
    }
};
