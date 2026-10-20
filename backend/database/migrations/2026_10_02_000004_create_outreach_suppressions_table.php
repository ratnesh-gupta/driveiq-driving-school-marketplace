<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-1103/1105: people who asked not to be contacted. Only hashes of the
 * email and phone are kept, so the list can be checked without holding the
 * contact details of someone who opted out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 8); // email | phone
            $table->char('value_hash', 64);
            $table->string('reason', 32);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['kind', 'value_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_suppressions');
    }
};
