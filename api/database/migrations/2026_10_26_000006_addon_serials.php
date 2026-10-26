<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on «الأرقام التسلسلية والضمان»: items flagged track_serial keep a record per
 * physical unit (serial no + warranty). A registry overlaid on stock — it does NOT
 * move quantities (normal purchase/sale flows do) — so a unit's identity, owner and
 * warranty are tracked from receipt to the customer, for lookups and warranty claims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->boolean('track_serial')->default(false)->after('track_stock');
            $t->unsignedSmallInteger('warranty_months')->nullable()->after('track_serial');
        });
        Schema::create('serial_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->string('serial_no', 80);
            // in_stock | sold | installed | returned | faulty | scrapped
            $t->string('status', 20)->default('in_stock');
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name', 150)->nullable();   // when no customer record
            $t->unsignedSmallInteger('warranty_months')->nullable();
            $t->date('warranty_start')->nullable();
            $t->date('warranty_until')->nullable();
            $t->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $t->string('out_ref', 60)->nullable();          // sale / job number it left on
            $t->foreignId('replaced_by_id')->nullable()->constrained('serial_units')->nullOnDelete();
            $t->date('delivered_at')->nullable();
            $t->string('location', 120)->nullable();        // site / installation location
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'serial_no']);
            $t->index(['tenant_id', 'item_id', 'status']);
            $t->index(['tenant_id', 'status', 'warranty_until']);
        });
        Schema::create('serial_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('serial_unit_id')->constrained('serial_units')->cascadeOnDelete();
            $t->string('type', 24);                         // received, opening, delivered, returned, faulty, replaced, scrapped, edited
            $t->string('from_status', 20)->nullable();
            $t->string('to_status', 20)->nullable();
            $t->string('ref', 80)->nullable();
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index('serial_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_events');
        Schema::dropIfExists('serial_units');
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn(['track_serial', 'warranty_months']));
    }
};
