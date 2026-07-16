<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * roles.assignable_types — capability config (decision DH4): which user types a
 * role may create/assign (delegated creation/assignment). JSON, nullable, seeded
 * with defaults (HierarchySeeder, sourced from config/hierarchy.php) but DORMANT:
 * no gate reads it until H2/H4. App\Models\Role casts it to an array. Additive +
 * reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('assignable_types')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('assignable_types');
        });
    }
};
