<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Richer item / supplier / purchase profiles for the full-page inventory screens. All nullable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('brand', 80)->nullable()->after('name_en');
            $table->string('model', 80)->nullable()->after('brand');
            $table->text('description')->nullable()->after('model');
            $table->foreignId('supplier_id')->nullable()->after('unit_id')->constrained('suppliers')->nullOnDelete();
            $table->decimal('reorder_qty', 12, 3)->nullable()->after('min_stock');
            $table->decimal('max_stock', 12, 3)->nullable()->after('reorder_qty');
            $table->string('location', 80)->nullable()->after('max_stock');   // shelf / bin
            $table->decimal('weight', 10, 3)->nullable()->after('location');  // kg
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('supplier_type', 20)->default('company')->after('name');
            $table->string('code', 40)->nullable()->after('supplier_type');
            $table->string('contact_person', 150)->nullable()->after('code');
            $table->string('phone2', 30)->nullable()->after('phone');
            $table->string('whatsapp', 30)->nullable()->after('phone2');
            $table->string('cr_number', 50)->nullable()->after('tax_number');
            $table->string('country', 60)->nullable()->after('address');
            $table->string('city', 80)->nullable()->after('country');
            $table->string('area', 120)->nullable()->after('city');
            $table->text('bank_details')->nullable()->after('area');
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('bank_details');
            $table->string('category', 40)->nullable()->after('payment_terms_days');
            $table->decimal('opening_balance', 12, 3)->default(0)->after('category');
            $table->string('tags', 255)->nullable()->after('opening_balance');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('reference', 80)->nullable()->after('supplier_invoice');
            $table->date('due_date')->nullable()->after('date');
            $table->string('payment_method', 20)->default('cash')->after('paid');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['brand', 'model', 'description', 'reorder_qty', 'max_stock', 'location', 'weight']);
        });
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn(['supplier_type', 'code', 'contact_person', 'phone2', 'whatsapp', 'cr_number', 'country', 'city', 'area', 'bank_details', 'payment_terms_days', 'category', 'opening_balance', 'tags']));
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['reference', 'due_date', 'payment_method']));
    }
};
