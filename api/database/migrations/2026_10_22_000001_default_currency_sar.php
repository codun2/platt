<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Platform currency is "ريال" (SAR) for now: new tenants default to SAR and existing OMR tenants are moved over. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('currency', 8)->default('SAR')->change();
        });
        DB::table('tenants')->where('currency', 'OMR')->update(['currency' => 'SAR']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('currency', 8)->default('OMR')->change();
        });
    }
};
