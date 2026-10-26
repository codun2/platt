<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medicine fields for items (used by the pharmacy sector): the active ingredient
 * (so alternatives sharing it can be found), strength, dosage form, and whether a
 * prescription is required. Generic enough for supplements / cosmetics too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->string('active_ingredient', 150)->nullable()->after('model');
            $t->string('strength', 60)->nullable()->after('active_ingredient');
            $t->string('dosage_form', 40)->nullable()->after('strength');
            $t->boolean('rx_required')->default(false)->after('dosage_form');
            $t->index(['tenant_id', 'active_ingredient']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->dropIndex(['tenant_id', 'active_ingredient']);
            $t->dropColumn(['active_ingredient', 'strength', 'dosage_form', 'rx_required']);
        });
    }
};
