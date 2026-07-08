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
        Schema::table('customers', function (Blueprint $table) {
            // Owner/assignment (attribution + filtering, not access control).
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            // Canonical E.164 form of phone for CTI caller-ID lookup.
            $table->string('phone_normalized')->nullable();
            // Lifecycle. Default 'active' backfills existing (they have transactions).
            $table->string('status')->default('active');
            $table->string('source')->nullable();

            $table->index('phone_normalized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn(['assigned_to', 'phone_normalized', 'status', 'source']);
        });
    }
};
