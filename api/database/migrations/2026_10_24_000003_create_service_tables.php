<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine: Service — work orders for garages, laundries, maintenance companies
 * and tailors. An order carries sector-specific details (JSON), labor / part
 * lines and a status flow; delivering it issues the invoice, deducts parts from
 * stock and posts their cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('specialty')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Price list of services (labor) and parts / materials without stock tracking.
        Schema::create('service_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 10)->default('labor');              // labor, part
            $table->decimal('price', 12, 3)->default(0);
            $table->decimal('cost', 12, 3)->default(0);
            $table->string('unit', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();               // walk-in snapshot
            $table->string('customer_phone', 30)->nullable();
            $table->string('title');                                    // the request / complaint
            $table->json('details')->nullable();                        // sector fields (vehicle, garment, pieces, site…)
            // received, in_progress, waiting_parts, ready, delivered, cancelled
            $table->string('status', 15)->default('received');
            $table->string('priority', 10)->default('normal');
            $table->foreignId('technician_id')->nullable()->constrained('service_technicians')->nullOnDelete();
            $table->dateTime('received_at');
            $table->dateTime('promised_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->decimal('estimate', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('service_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('kind', 10)->default('labor');               // labor, part
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();         // stocked part
            $table->foreignId('catalog_id')->nullable()->constrained('service_catalog')->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_lines');
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('service_catalog');
        Schema::dropIfExists('service_technicians');
    }
};
