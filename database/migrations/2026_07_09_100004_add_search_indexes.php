<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for the hot search/date-filter paths (deferred from Batch 2).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('purchased_at');
            $table->index(['reseller_id', 'purchased_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('name');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['purchased_at']);
            $table->dropIndex(['reseller_id', 'purchased_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['email']);
        });
    }
};
