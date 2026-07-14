<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Backfills customers.created_by for rows that predate the column (decision
 * D3-A): the current owner (assigned_to) is the best available proxy for who
 * first entered the customer. Rows without an owner stay null (manager-visible
 * only once scoping lands in B1).
 *
 * Idempotent: only touches rows still missing a creator. Shared by the
 * migration and its test so the logic has one definition.
 */
final class CustomerCreatedByBackfill
{
    /**
     * @return int number of rows updated
     */
    public static function run(): int
    {
        return DB::table('customers')
            ->whereNull('created_by')
            ->whereNotNull('assigned_to')
            ->update(['created_by' => DB::raw('assigned_to')]);
    }
}
