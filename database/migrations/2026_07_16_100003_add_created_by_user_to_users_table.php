<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.created_by_user — who provisioned this account: the delegated-creation
 * trail (DH1/DH4, e.g. a manager creating a Sales/CS/Maintenance). Nullable and
 * set server-side (NOT mass-assignable), mirroring customers.created_by. Existing
 * rows stay null. Dormant in H1 — nothing reads it yet. Additive + reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('created_by_user')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->index('created_by_user');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user');
        });
    }
};
