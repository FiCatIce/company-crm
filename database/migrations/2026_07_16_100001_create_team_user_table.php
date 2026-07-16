<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * team_user — team membership pivot (decision DH1).
 *
 * A pivot (rather than a `users.team_id` column) is a deliberate future-proof
 * choice: DH2 makes it one-team-per-user for now, but a pivot lets DH6 multi-team
 * arrive with NO schema change. The 1:1 rule (DH2) is therefore enforced in
 * APPLICATION logic at creation time (a later batch) — NOT by a DB unique on
 * user_id, which would hard-block DH6. The unique(team_id, user_id) here only
 * prevents duplicate membership rows.
 *
 * `role_in_team` is a forward hook (e.g. mark the team's manager). Dormant in H1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_in_team')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
    }
};
