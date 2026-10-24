<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine: Rental — real estate (buildings / units), car rental (fleet) and
 * installment sales. Assets are leased through contracts; a contract generates
 * its installment schedule; payments settle installments and post to the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10)->default('owner');            // owner, broker
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('national_id', 40)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);     // % management fee / brokerage
            $table->string('bank_name')->nullable();
            $table->string('iban', 40)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rental_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('rental_assets')->nullOnDelete(); // unit inside a building
            $table->foreignId('owner_id')->nullable()->constrained('rental_owners')->nullOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name');
            // building, apartment, villa, shop, office, warehouse, land, vehicle, other
            $table->string('type', 20)->default('apartment');
            $table->string('status', 15)->default('available');      // available, rented, maintenance, inactive
            $table->string('city', 80)->nullable();
            $table->string('address')->nullable();
            $table->decimal('area', 10, 2)->nullable();               // m²
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->string('floor', 20)->nullable();
            $table->boolean('furnished')->default(false);
            $table->string('electricity_meter', 40)->nullable();
            $table->string('water_meter', 40)->nullable();
            // vehicle
            $table->string('plate_no', 30)->nullable();
            $table->string('make', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 30)->nullable();
            $table->string('vin', 40)->nullable();
            $table->unsignedInteger('odometer')->nullable();
            // pricing hints for new contracts
            $table->decimal('rent_amount', 12, 3)->default(0);
            $table->string('rent_frequency', 12)->default('monthly');
            $table->decimal('deposit', 12, 3)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('rental_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('kind', 12)->default('lease');             // lease, installment (sale on installments)
            $table->foreignId('asset_id')->constrained('rental_assets');
            $table->foreignId('customer_id')->constrained('customers');
            $table->date('start_date');
            $table->date('end_date');
            // daily, weekly, monthly, quarterly, semiannual, annual, once
            $table->string('frequency', 12)->default('monthly');
            $table->decimal('rent_amount', 12, 3);                    // per period
            $table->decimal('down_payment', 12, 3)->default(0);       // installment sales
            $table->decimal('total', 12, 3)->default(0);              // sum of schedule
            $table->decimal('paid', 12, 3)->default(0);
            $table->decimal('deposit', 12, 3)->default(0);            // refundable security deposit
            $table->decimal('deposit_paid', 12, 3)->default(0);
            $table->string('deposit_status', 10)->default('none');    // none, held, returned
            $table->string('status', 12)->default('active');          // active, ended, terminated
            $table->date('terminated_at')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('rental_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('rental_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->date('due_date');
            $table->date('period_end')->nullable();
            $table->decimal('amount', 12, 3);
            $table->decimal('paid', 12, 3)->default(0);
            $table->string('status', 10)->default('due');             // due, partial, paid, cancelled
            $table->date('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'due_date']);
        });

        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('rental_contracts')->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('rental_installments')->nullOnDelete();
            $table->string('kind', 15)->default('rent');              // rent, deposit, deposit_return
            $table->decimal('amount', 12, 3);
            $table->string('method', 10)->default('cash');
            $table->date('date');
            $table->string('reference', 60)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rental_installments');
        Schema::dropIfExists('rental_contracts');
        Schema::dropIfExists('rental_assets');
        Schema::dropIfExists('rental_owners');
    }
};
