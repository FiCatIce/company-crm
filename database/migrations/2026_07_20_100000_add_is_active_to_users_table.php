<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.is_active — the account lifecycle switch (DESIGN_HIERARCHY.md batch H7b).
 * False blocks every login path; it does NOT touch the user's data: assigned_to and
 * sales_assignee rows stay exactly as they are, so reactivation restores the account
 * whole (the decision: deactivation is REVERSIBLE, transferring a book is a separate,
 * explicit action — H7c). Set server-side only (NOT mass-assignable), like
 * created_by_user. Existing rows default to active. Additive + reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('extension');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
