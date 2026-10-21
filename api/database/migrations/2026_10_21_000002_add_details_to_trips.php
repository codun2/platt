<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer trip profile for Hajj / Umrah / tourism operators:
 * transport & airline, Makkah/Madinah hotels & nights, supervisor, boarding point.
 * All nullable so existing trips are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->after('name');
            $table->string('transport', 30)->nullable()->after('destination');   // flight | bus | private | mixed
            $table->string('airline', 80)->nullable()->after('transport');
            $table->string('flight_no', 40)->nullable()->after('airline');
            $table->string('boarding_point', 120)->nullable()->after('flight_no'); // gathering / departure point
            $table->string('hotel_makkah', 120)->nullable()->after('boarding_point');
            $table->unsignedSmallInteger('nights_makkah')->nullable()->after('hotel_makkah');
            $table->string('hotel_madinah', 120)->nullable()->after('nights_makkah');
            $table->unsignedSmallInteger('nights_madinah')->nullable()->after('hotel_madinah');
            $table->string('supervisor', 120)->nullable()->after('nights_madinah');
            $table->string('supervisor_phone', 30)->nullable()->after('supervisor');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'transport', 'airline', 'flight_no', 'boarding_point',
                'hotel_makkah', 'nights_makkah', 'hotel_madinah', 'nights_madinah',
                'supervisor', 'supervisor_phone',
            ]);
        });
    }
};
