<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline-first support:
 *  - idempotency_keys: a retried write (offline outbox, flaky network) replays the
 *    stored response instead of running twice;
 *  - sales: client_ref (the device's temporary number, unique per tenant), when it
 *    was really sold, and a flag when stock was short at upload time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->longText('response');
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'key']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('client_ref', 60)->nullable()->after('number');
            $table->boolean('offline')->default(false)->after('status');
            $table->dateTime('sold_at')->nullable()->after('date');
            $table->boolean('stock_short')->default(false)->after('offline');
            $table->unique(['tenant_id', 'client_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'client_ref']);
            $table->dropColumn(['client_ref', 'offline', 'sold_at', 'stock_short']);
        });
        Schema::dropIfExists('idempotency_keys');
    }
};
