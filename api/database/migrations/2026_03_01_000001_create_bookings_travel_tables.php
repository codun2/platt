<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Engine: Bookings (halls / chalets / rooms) + Travel (hajj, umrah, tourism). Phase 3. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type', 20)->default('hall');           // hall, chalet, room, other
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('price_day', 12, 3)->default(0);       // full day
            $table->decimal('price_night', 12, 3)->default(0);     // overnight / per night
            $table->decimal('price_hour', 12, 3)->default(0);
            $table->decimal('deposit', 12, 3)->default(0);          // default security deposit (تأمين)
            $table->string('location')->nullable();
            $table->string('color', 10)->default('#e11d48');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->decimal('price', 12, 3)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('booking_units')->nullOnDelete(); // null = all units
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('unit_id')->constrained('booking_units');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();            // walk-in snapshot
            $table->string('customer_phone')->nullable();
            $table->string('period', 10)->default('day');           // day, night, hour
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('qty', 8, 2)->default(1);               // days / nights / hours
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('services_total', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->decimal('paid', 12, 3)->default(0);
            $table->decimal('deposit', 12, 3)->default(0);          // security deposit required
            $table->decimal('deposit_paid', 12, 3)->default(0);
            $table->string('deposit_status', 12)->default('pending'); // pending, held, returned, forfeited
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->string('event_type')->nullable();               // wedding, meeting ...
            $table->unsignedInteger('guests')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'unit_id', 'start_at']);
        });

        Schema::create('booking_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('booking_services')->nullOnDelete();
            $table->string('name');
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('price', 12, 3);
            $table->decimal('total', 12, 3);
        });

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('payment');        // payment, deposit, refund, deposit_return
            $table->decimal('amount', 12, 3);
            $table->string('method', 20)->default('cash');
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ---- Travel ----
        Schema::create('travel_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('umrah');          // umrah, hajj, tourism
            $table->decimal('price', 12, 3)->default(0);
            $table->text('includes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('umrah');
            $table->string('destination')->nullable();
            $table->date('departure_date');
            $table->date('return_date')->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->decimal('price', 12, 3)->default(0);
            $table->foreignId('package_id')->nullable()->constrained('travel_packages')->nullOnDelete();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_travelers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('passport_no')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('nationality')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->foreignId('package_id')->nullable()->constrained('travel_packages')->nullOnDelete();
            $table->decimal('price', 12, 3)->default(0);
            $table->decimal('paid', 12, 3)->default(0);
            $table->enum('status', ['registered', 'confirmed', 'cancelled'])->default('registered');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('traveler_id')->constrained('trip_travelers')->cascadeOnDelete();
            $table->decimal('amount', 12, 3);
            $table->string('method', 20)->default('cash');
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('trip_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40)->default('other');     // flights, hotel, transport, visa, food, other
            $table->decimal('amount', 12, 3);
            $table->date('date');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['trip_expenses', 'trip_payments', 'trip_travelers', 'trips', 'travel_packages', 'booking_payments', 'booking_lines', 'bookings', 'booking_services', 'booking_units'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
