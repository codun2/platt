<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add-on «الولاء والمحفظة»: customer points (earned on purchases, redeemed as a
 * discount) and a prepaid wallet balance (top-ups, spent as a payment method).
 * Every change is a row in loyalty_transactions; customers hold the running totals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->decimal('points', 14, 3)->default(0)->after('credit_limit');
            $t->decimal('wallet_balance', 14, 3)->default(0)->after('points');
        });
        Schema::create('loyalty_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // earn, redeem, adjust, expire | topup, spend, refund (wallet)
            $t->string('type', 20);
            $t->decimal('points', 14, 3)->default(0);     // + earned / − redeemed
            $t->decimal('amount', 14, 3)->default(0);     // wallet: + top-up / − spend; redeem: value of the points
            $t->decimal('points_after', 14, 3)->default(0);
            $t->decimal('wallet_after', 14, 3)->default(0);
            $t->string('method', 20)->nullable();         // top-up / refund paid by cash, card, transfer
            $t->string('ref_type', 30)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('reference', 60)->nullable();      // e.g. sale number
            $t->string('note')->nullable();
            $t->boolean('reversed')->default(false);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'customer_id', 'id']);
            $t->index(['ref_type', 'ref_id']);
        });
        Schema::table('sales', function (Blueprint $t) {
            $t->decimal('points_redeemed', 14, 3)->default(0)->after('discount');
            $t->decimal('points_discount', 14, 3)->default(0)->after('points_redeemed');
            $t->decimal('points_earned', 14, 3)->default(0)->after('points_discount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn(['points_redeemed', 'points_discount', 'points_earned']));
        Schema::dropIfExists('loyalty_transactions');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['points', 'wallet_balance']));
    }
};
