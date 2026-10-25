<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on «صفحة الطلب أونلاين»: a public page (rekaz…/app/store/<handle>) listing the
 * tenant's items; customers order without an account and it arrives as a pre-order
 * (channel «online»). Items can be hidden from the page one by one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->boolean('show_online')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn('show_online'));
    }
};
