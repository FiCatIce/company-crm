<?php

use App\Support\CustomerCreatedByBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds customers.created_by — the immutable "who first entered this customer"
 * attribution that becomes the Sales visibility gate (DESIGN_RBAC.md §4.1). It
 * coexists with the mutable assigned_to (owner). NOT mass-assignable: set
 * server-side on create so it cannot be forged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('assigned_to')
                ->constrained('users')->nullOnDelete();
            $table->index('created_by');
        });

        // D3-A backfill: legacy rows inherit created_by from their owner.
        CustomerCreatedByBackfill::run();
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
