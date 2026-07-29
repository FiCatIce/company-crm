<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L3-A white-label storage (ketok D1): admin-editable display-label overrides,
 * key = role slug ('sales', 'cs', …) → label = custom display name ('Account
 * Manager'). Read exclusively through App\Support\TeamRoleLabels (override →
 * config default → headline fallback); written only by the L3-C admin UI.
 * The 'admin' slug is excluded at the resolution layer (D2) — a row for it,
 * however it got here, never applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_overrides');
    }
};
