<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L2-A (DESIGN_L2_DEPRECATE_RESELLER.md) — relax transactions.reseller_id to
 * nullable so new transactions can be recorded WITHOUT a reseller, the first step
 * of deprecating the entity. Mirrors what the CTI batch already did for
 * customers.reseller_id. The column, its data, and the FK stay in place — nothing
 * is dropped here (that is the destructive L2-D). Existing rows keep their value.
 *
 * Reversible: down() restores NOT NULL. It assumes no null rows exist yet — true
 * right after this batch, before any reseller-less transaction is created — which
 * is the normal precondition for rolling a migration back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable(false)->change();
        });
    }
};
