<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on «الدفعات وتواريخ الصلاحية»: items flagged track_expiry keep their stock in
 * batches (batch no + expiry). Every stock-out takes the earliest expiry first (FEFO);
 * batch_moves remembers which batches a document used, so a cancel puts it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->boolean('track_expiry')->default(false)->after('track_stock');
        });
        Schema::table('purchase_items', function (Blueprint $t) {
            $t->string('batch_no', 60)->nullable();
            $t->date('expires_on')->nullable();
        });
        Schema::create('item_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $t->string('batch_no', 60)->nullable();
            $t->date('expires_on')->nullable();
            $t->decimal('qty_in', 14, 3)->default(0);
            $t->decimal('qty_left', 14, 3)->default(0);
            $t->decimal('unit_cost', 14, 3)->nullable();
            $t->string('ref_type', 30)->nullable();      // where it came from (purchase, transfer, adjust…)
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'item_id', 'warehouse_id', 'expires_on']);
        });
        Schema::create('batch_moves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('item_batches')->cascadeOnDelete();
            $t->decimal('qty', 14, 3);                   // − taken / + put back
            $t->string('type', 30);
            $t->string('ref_type', 30)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_moves');
        Schema::dropIfExists('item_batches');
        Schema::table('purchase_items', fn (Blueprint $t) => $t->dropColumn(['batch_no', 'expires_on']));
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn('track_expiry'));
    }
};
