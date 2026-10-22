<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra booking details for the full-page booking screens. All nullable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('event_type');      // walk_in | phone | whatsapp | online | agent
            $table->string('customer_email', 150)->nullable()->after('customer_phone');
            $table->unsignedSmallInteger('adults')->nullable()->after('guests');
            $table->unsignedSmallInteger('children')->nullable()->after('adults');
            $table->text('special_requests')->nullable()->after('note');       // shows to staff on the booking
            $table->text('internal_note')->nullable()->after('special_requests');
            $table->timestamp('checked_in_at')->nullable()->after('internal_note');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['source', 'customer_email', 'adults', 'children', 'special_requests', 'internal_note', 'checked_in_at', 'checked_out_at']));
    }
};
