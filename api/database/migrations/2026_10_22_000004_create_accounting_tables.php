<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting (core): chart of accounts, double-entry journal, expenses.
 * Sales invoices, POS sales, bookings and payroll post into the journal through
 * App\Services\LedgerService so the financial reports stay live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false);
            $table->string('system_key', 40)->nullable();     // cash, bank, ar, ap, sales, vat_out ... (auto-posting targets)
            $table->decimal('opening_balance', 14, 3)->default(0); // signed by natural side (debit +)
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'system_key']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->date('date');
            $table->string('reference', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->enum('status', ['draft', 'posted'])->default('posted');
            $table->string('source_type', 30)->nullable();   // manual | invoice | invoice_payment | sale | sale_payment | expense | booking_payment | payroll | reversal
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('total', 14, 3)->default(0);     // sum of debits
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('debit', 14, 3)->default(0);
            $table->decimal('credit', 14, 3)->default(0);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->index('account_id');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->date('date');
            $table->foreignId('account_id')->constrained('accounts');                 // expense category account
            $table->foreignId('paid_from_account_id')->constrained('accounts');       // cash / bank
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('payee', 150)->nullable();
            $table->decimal('amount', 14, 3);                 // before tax
            $table->decimal('tax', 14, 3)->default(0);
            $table->decimal('total', 14, 3);
            $table->string('payment_method', 20)->default('cash');
            $table->string('reference', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['expenses', 'journal_lines', 'journal_entries', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
