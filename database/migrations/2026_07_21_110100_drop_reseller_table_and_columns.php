<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L2-D (DESIGN_L2_DEPRECATE_RESELLER.md) — the DESTRUCTIVE half. The distributor
 * name is already archived onto customers.reseller_name_legacy by the preceding
 * migration, so this severs the entity for good: the reseller_id columns (+ their
 * FKs and the composite search index) and the resellers table itself.
 *
 * POINT OF NO RETURN for the tree/relation/attribution. down() restores the empty
 * SCHEMA (so a rollback does not fatal), but the reseller rows and every row's
 * reseller_id value are gone — only the archived name survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop the composite search index first — it depends on reseller_id.
            $table->dropIndex(['reseller_id', 'purchased_at']);
            $table->dropConstrainedForeignId('reseller_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reseller_id');
        });

        Schema::dropIfExists('resellers');
    }

    public function down(): void
    {
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('resellers')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['reseller_id', 'purchased_at']);
        });
    }
};
