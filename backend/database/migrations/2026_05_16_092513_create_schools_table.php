<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('locality_id')->constrained('localities')->cascadeOnDelete();
            $table->text('address');
            $table->string('phone');
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->float('rating')->default(0);
            $table->integer('review_count')->default(0);
            $table->boolean('verified')->default(false);
            $table->boolean('has_pickup')->default(false);
            $table->boolean('women_instructor')->default(false);
            $table->boolean('weekend_classes')->default(false);
            $table->json('vehicle_types')->nullable();
            $table->json('transmission')->nullable();
            $table->float('price_from')->default(0);
            $table->float('price_to')->default(0);
            $table->string('timings')->nullable();
            $table->json('service_areas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
