<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            // History belongs to the customer; dies with them.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Handling agent; kept (nulled) if the user is removed. Null = system/unmatched.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);                       // InteractionType enum
            $table->string('direction', 3)->nullable();       // in|out (null for notes)
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('outcome', 20)->nullable();        // InteractionOutcome (calls)
            $table->unsignedInteger('duration_sec')->nullable();
            $table->timestamp('occurred_at')->index();        // when it happened
            $table->string('source', 10)->default('manual');  // manual|cti|import
            $table->string('external_ref', 64)->nullable()->unique(); // PBX call id (idempotency)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'occurred_at']);    // timeline
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
