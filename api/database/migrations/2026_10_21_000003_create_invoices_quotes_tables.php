<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM (core): sales invoices + quotes for every sector. Lines may reference an
 * inventory item or be free text (services). Quotes convert into invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->string('subject', 200)->nullable();     // موضوع العرض
            $table->string('reference', 80)->nullable();    // مرجع العميل / رقم الطلب
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->enum('discount_type', ['amount', 'percent'])->default('amount');
            $table->decimal('discount_value', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);  // resolved amount
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->text('terms')->nullable();              // شروط العرض
            $table->text('notes')->nullable();              // ملاحظات تظهر للعميل
            $table->text('internal_notes')->nullable();     // ملاحظات داخلية
            $table->unsignedBigInteger('converted_invoice_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('qty', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'cancelled'])->default('draft');
            $table->string('subject', 200)->nullable();
            $table->string('reference', 80)->nullable();    // رقم أمر الشراء / المرجع
            $table->decimal('subtotal', 12, 3)->default(0);
            $table->enum('discount_type', ['amount', 'percent'])->default('amount');
            $table->decimal('discount_value', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->decimal('paid', 12, 3)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'due_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('qty', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 3)->default(0);
            $table->decimal('discount', 12, 3)->default(0);
            $table->decimal('total', 12, 3)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 3);
            $table->string('method', 20)->default('cash');   // cash, card, transfer, cheque
            $table->date('date');
            $table->string('reference', 80)->nullable();    // رقم الحوالة / الشيك
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreign('converted_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', fn (Blueprint $t) => $t->dropForeign(['converted_invoice_id']));
        foreach (['invoice_payments', 'invoice_items', 'invoices', 'quote_items', 'quotes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
