<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sales_assignee — many-to-many between a Sales user and the CS/Maintenance users
 * assigned to help them (decision DH5). One CS can serve many Sales; one Sales can
 * have many CS/Maintenance. Created DORMANT: no scope reads it until H3.
 *
 * cascadeOnDelete on both sides: an assignment is meaningless once either party is
 * gone (the customers themselves are untouched — they hang off created_by/
 * assigned_to, handled by the offboarding flow, not this pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_assignee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sales_user_id', 'assignee_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_assignee');
    }
};
