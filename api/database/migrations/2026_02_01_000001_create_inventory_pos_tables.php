<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine: Inventory + POS (phase 2). Every table is tenant-scoped.
 * Also adds CRM customers (core) since sales need them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(5)->after('currency');   // VAT % (Oman = 5)
            $table->json('settings')->nullable()->after('tax_rate');
        });

        // Per-tenant document numbering (INV-2026-000001 ...).
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->unsignedInteger('year');
            $table->unsignedInteger('last')->default(0);
            $table->unique(['tenant_id', 'key', 'year']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 60)->nullable();
            $table->string('barcode', 80)->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('cost_price', 12, 3)->default(0);
            $table->decimal('sale_price', 12, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('image')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'barcode']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 3);                // signed: + in, - out
            $table->decimal('balance_after', 12, 3)->default(0);
            $table->string('type', 30);                   // purchase, sale, sale_cancel, adjustment, transfer_in, transfer_out, opening
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->decimal('unit_cost', 12, 3)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'item_id']);
            $table->index(['ref_type', 'ref_id']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('date');
            $table->string('supplier_invoice')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('tax', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->decimal('paid', 12, 3)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_cost', 12, 3);
            $table->decimal('total', 12, 3);
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 3)->default(0);
            $table->decimal('expected_cash', 12, 3)->nullable();
            $table->decimal('closing_cash', 12, 3)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('date');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->decimal('paid', 12, 3)->default(0);
            $table->string('payment_method', 20)->default('cash');   // cash, card, transfer, credit
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->string('name');                         // snapshot
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 12, 3);
            $table->decimal('unit_cost', 12, 3)->default(0); // snapshot for profit reports
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('total', 12, 3);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 3);
            $table->string('method', 20)->default('cash');
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sale_payments', 'sale_items', 'sales', 'shifts', 'purchase_items', 'purchases', 'stock_movements', 'stock_levels', 'items', 'warehouses', 'units', 'categories', 'suppliers', 'customers', 'sequences'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['tax_rate', 'settings']));
    }
};
