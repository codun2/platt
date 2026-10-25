<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on «الطلبات المسبقة والتوصيل»: orders taken now for a later date (cakes,
 * trays, catering, bulk orders), with a deposit, a preparation board and
 * pickup / delivery. Handing an order over issues its invoice (stock + ledger),
 * and the deposits held (2600 customer_advances) are applied to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('number', 30);
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name', 150)->nullable();
            $t->string('customer_phone', 30)->nullable();
            $t->string('channel', 20)->default('shop');        // shop, phone, whatsapp, online
            $t->string('fulfilment', 20)->default('pickup');   // pickup, delivery
            $t->dateTime('due_at');
            $t->text('address')->nullable();
            $t->string('driver', 120)->nullable();
            $t->decimal('delivery_fee', 14, 3)->default(0);
            // new → confirmed → preparing → ready → out (delivery) → delivered | cancelled
            $t->string('status', 20)->default('new');
            $t->decimal('subtotal', 14, 3)->default(0);
            $t->decimal('discount', 14, 3)->default(0);
            $t->decimal('tax_rate', 6, 3)->default(0);
            $t->decimal('tax', 14, 3)->default(0);
            $t->decimal('total', 14, 3)->default(0);
            $t->decimal('paid', 14, 3)->default(0);            // deposits held (net of refunds)
            $t->text('notes')->nullable();                     // for the customer / the team
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'number']);
            $t->index(['tenant_id', 'status', 'due_at']);
        });
        Schema::create('order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');                          // e.g. «كيكة شوكولا 2 كغ — اكتب: عيد ميلاد سعيد»
            $t->string('unit', 30)->nullable();
            $t->decimal('qty', 14, 3)->default(1);
            $t->decimal('unit_price', 14, 3)->default(0);
            $t->decimal('discount', 14, 3)->default(0);
            $t->decimal('total', 14, 3)->default(0);
            $t->unsignedInteger('sort')->default(0);
        });
        Schema::create('order_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20)->default('deposit');         // deposit, refund
            $t->decimal('amount', 14, 3);
            $t->string('method', 20)->default('cash');
            $t->date('date');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
