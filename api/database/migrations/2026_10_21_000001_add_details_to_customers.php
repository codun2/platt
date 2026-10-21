<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer customer profile — generic fields that serve every sector
 * (retail, wholesale, hotels, chalets, halls, travel, rental, real estate,
 *  garages, schools, law firms ...). All nullable so existing rows are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // identity / type
            $table->string('customer_type', 20)->default('individual')->after('name'); // individual | company
            $table->string('code', 40)->nullable()->after('customer_type');             // optional customer code
            // extra contact
            $table->string('phone2', 30)->nullable()->after('phone');
            $table->string('whatsapp', 30)->nullable()->after('phone2');
            // official identity (rental, hotels, travel, real estate ...)
            $table->string('id_type', 20)->nullable()->after('tax_number');   // national_id | passport | resident_id | commercial_reg
            $table->string('id_number', 50)->nullable()->after('id_type');
            $table->string('nationality', 60)->nullable()->after('id_number');
            $table->date('birth_date')->nullable()->after('nationality');
            $table->string('gender', 10)->nullable()->after('birth_date');    // male | female
            // business / org
            $table->string('company_name', 150)->nullable()->after('gender');
            $table->string('contact_person', 150)->nullable()->after('company_name');
            // location breakdown
            $table->string('country', 60)->nullable()->after('address');
            $table->string('city', 80)->nullable()->after('country');
            $table->string('area', 120)->nullable()->after('city');
            // classification / financial
            $table->string('category', 40)->nullable()->after('area');        // vip | regular | wholesale | ...
            $table->decimal('credit_limit', 12, 3)->default(0)->after('category');
            $table->decimal('opening_balance', 12, 3)->default(0)->after('credit_limit');
            $table->unsignedInteger('payment_terms_days')->nullable()->after('opening_balance'); // credit days
            $table->string('tags', 255)->nullable()->after('payment_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type', 'code', 'phone2', 'whatsapp', 'id_type', 'id_number',
                'nationality', 'birth_date', 'gender', 'company_name', 'contact_person',
                'country', 'city', 'area', 'category', 'credit_limit', 'opening_balance',
                'payment_terms_days', 'tags',
            ]);
        });
    }
};
