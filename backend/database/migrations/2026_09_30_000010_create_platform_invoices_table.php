<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIQ-803: DriveQ's invoices to schools for plans. Kept apart from the
 * schools' own invoices/payments (package sales to learners), which school
 * staff may mark paid; only a platform admin can settle these. Seller and
 * buyer details are snapshotted so an issued invoice never changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('plan_code', 32);
            $table->unsignedTinyInteger('months');
            $table->string('billed_to_name');
            $table->string('billed_to_gstin', 15)->nullable();
            $table->json('seller');
            $table->unsignedInteger('subtotal');
            $table->decimal('gst_rate', 5, 2);
            $table->unsignedInteger('gst_amount');
            $table->unsignedInteger('total');
            $table->string('currency', 3)->default('INR');
            $table->string('status', 16)->default('issued'); // issued | paid | void
            $table->timestamp('issued_at');
            $table->timestamp('due_at');
            $table->string('payment_reference')->nullable(); // UTR / transaction id
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            // For online collection later (Razorpay order id).
            $table->string('provider_order_id')->nullable()->index();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['status', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};
