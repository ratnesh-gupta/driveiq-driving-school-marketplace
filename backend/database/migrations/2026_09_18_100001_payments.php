<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('learner_id')->nullable()->index();
            $table->string('invoice_number')->unique();
            $table->string('purpose'); // package|subscription|other
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedInteger('amount'); // INR whole rupees
            $table->string('currency')->default('INR');
            $table->string('status')->default('draft')->index(); // draft|issued|paid|void
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->json('line_items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('learner_id')->references('id')->on('learners')->nullOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->index();
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->unsignedBigInteger('learner_id')->nullable()->index();
            $table->unsignedBigInteger('payer_user_id')->nullable();
            $table->string('purpose'); // package|subscription|other
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency')->default('INR');
            $table->string('method')->default('manual'); // manual|cash|upi|card|razorpay|bank
            $table->string('status')->default('pending')->index(); // pending|paid|failed|refunded
            $table->string('provider')->nullable(); // razorpay|manual
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable();
            $table->string('receipt')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('learner_id')->references('id')->on('learners')->nullOnDelete();
            $table->foreign('payer_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
