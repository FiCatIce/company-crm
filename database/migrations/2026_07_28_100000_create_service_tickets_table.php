<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 (DESIGN_MAINTENANCE.md) — the service-ticket table, dormant: no route,
 * policy, or preset touches it yet. Activation lands in M2.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            // Service history belongs to the customer; dies with them (same rule
            // as interactions).
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // The unit under repair. transaction_id is the PREFERRED anchor (it
            // carries product + purchase date + computed warranty); product_id +
            // item_description cover walk-ins bought elsewhere. Validation (M2)
            // requires at least one of the three.
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description')->nullable();
            $table->string('serial_no', 64)->nullable();
            $table->text('complaint');                          // keluhan customer
            $table->text('work_notes')->nullable();             // catatan pengerjaan berjalan
            $table->text('resolution')->nullable();             // wajib saat -> done (M2)
            $table->string('coverage', 10)->default('unknown'); // TicketCoverage enum (D4: no money)
            $table->string('priority', 10)->default('normal');  // TicketPriority enum (D7)
            $table->string('status', 15)->default('received');  // TicketStatus enum (D2)
            // Who works it (null = pool awaiting a claim) / who logged it. Both
            // survive user removal as null (the ticket outlives the staffing).
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Lifecycle stamps — the status history without an event table.
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'received_at']);      // 360 history section
            $table->index(['status', 'assigned_to']);           // list filters + pool
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_tickets');
    }
};
