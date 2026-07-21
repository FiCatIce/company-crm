<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L2-D (DESIGN_L2_DEPRECATE_RESELLER.md, decision #1 = ARCHIVE-then-drop) — the
 * FIRST half: snapshot each customer's distributor NAME onto a plain text column
 * BEFORE the destructive drop that follows. After the reseller entity is gone the
 * only trace worth keeping is "which distributor did this account originally come
 * through", and that survives here as history — no tree, no relation, no FK.
 *
 * Transactions are NOT archived separately: a transaction's reseller always
 * equalled its customer's (enforced by the data-integrity rule retired in L2-A),
 * so the customer snapshot already covers it.
 *
 * Runs its snapshot with the query builder (not the Reseller model), because the
 * NEXT migration deletes that model — a migration must never depend on app code
 * that a later migration removes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('reseller_name_legacy')->nullable()->after('reseller_id');
        });

        // Correlated scalar subquery — portable across pgsql (dev) and sqlite
        // (tests). On a fresh test DB customers is empty, so this is a no-op; on
        // dev it fills the snapshot for every row that still carries a reseller.
        DB::statement(<<<'SQL'
            UPDATE customers
            SET reseller_name_legacy = (
                SELECT name FROM resellers WHERE resellers.id = customers.reseller_id
            )
            WHERE reseller_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('reseller_name_legacy');
        });
    }
};
