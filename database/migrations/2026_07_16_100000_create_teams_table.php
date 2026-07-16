<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teams — the org unit a book of customers rolls up to via its sales members
 * (DESIGN_HIERARCHY.md L1 / decision DH1). A manager will see customers by TEAM
 * roll-up (DH3); that scoping lands in a later batch. This batch (H1) creates the
 * DORMANT structure only — no scope/dashboard/route reads it yet.
 *
 * `type` and `parent_id` are forward hooks (DH6 / L4): today every row is a plain
 * 'team', but region/division levels and a nested team tree can layer on later
 * with NO schema change. Additive, nullable, reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // L4 hook: 'team' now; 'region'/'division' later. A plain string (not
            // an enum) so a new level needs no migration.
            $table->string('type')->default('team');
            // L4 hook: nested team tree (region -> division -> team). Unused in L1.
            $table->foreignId('parent_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamps();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
