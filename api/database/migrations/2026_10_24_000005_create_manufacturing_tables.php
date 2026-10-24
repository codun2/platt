<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine: Manufacturing — recipes / bills of materials and production orders.
 * Completing an order consumes raw materials from stock and adds the finished
 * product at the actual material cost per unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');               // finished product
            $table->string('name');
            $table->decimal('output_qty', 12, 3)->default(1);                 // yield per batch
            $table->decimal('extra_cost', 12, 3)->default(0);                 // labor / packaging / overhead per batch
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');               // raw material
            $table->decimal('qty', 12, 3);                                    // per batch
            $table->decimal('waste_pct', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('bom_id')->constrained('boms');
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('planned_qty', 12, 3);
            $table->decimal('actual_qty', 12, 3)->nullable();
            $table->string('status', 12)->default('planned');                 // planned, in_progress, completed, cancelled
            $table->date('planned_date')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->decimal('material_cost', 14, 3)->default(0);
            $table->decimal('extra_cost', 14, 3)->default(0);
            $table->decimal('unit_cost', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('qty_required', 12, 3);
            $table->decimal('qty_consumed', 12, 3)->nullable();
            $table->decimal('unit_cost', 12, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_lines');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('boms');
    }
};
