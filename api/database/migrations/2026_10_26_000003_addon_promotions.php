<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on «العروض والكوبونات»: automatic offers (percent / amount off, buy X get Y,
 * bill discount above a minimum) limited by dates, weekdays and hours, plus coupon
 * codes typed at the till. Applied by PromotionService on POS checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('type', 20);                    // percent, amount, bxgy, bill_percent, bill_amount
            $t->string('applies_to', 20)->default('all'); // all, categories, items (line offers)
            $t->json('category_ids')->nullable();
            $t->json('item_ids')->nullable();
            $t->decimal('value', 14, 3)->default(0);   // % or amount (per unit for «amount», on the bill for bill_*)
            $t->unsignedInteger('buy_qty')->default(0); // bxgy: buy N …
            $t->unsignedInteger('get_qty')->default(0); // … get M free
            $t->decimal('min_total', 14, 3)->default(0);
            $t->decimal('max_discount', 14, 3)->nullable();
            $t->string('code', 40)->nullable();        // set = coupon (only when typed)
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->json('days')->nullable();              // [0..6] Sunday=0; null = every day
            $t->time('time_from')->nullable();
            $t->time('time_to')->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->decimal('discount_given', 14, 3)->default(0);
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'is_active']);
            $t->unique(['tenant_id', 'code']);
        });
        Schema::table('sales', function (Blueprint $t) {
            $t->decimal('promo_discount', 14, 3)->default(0)->after('points_earned');
            $t->string('coupon_code', 40)->nullable()->after('promo_discount');
            $t->json('promotions')->nullable()->after('coupon_code');   // [{id, name, amount}]
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn(['promo_discount', 'coupon_code', 'promotions']));
        Schema::dropIfExists('promotions');
    }
};
