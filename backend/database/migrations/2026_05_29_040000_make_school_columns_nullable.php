<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->foreignId('locality_id')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->foreignId('locality_id')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
        });
    }
};
