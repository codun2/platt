<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rakeez Core — multi-tenant platform tables.
 * See ARCHITECTURE.md §2 for the data model.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Subscription plans sold by the platform.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('price_monthly', 10, 3)->default(0);
            $table->unsignedInteger('max_users')->default(5);
            $table->text('description_ar')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Sector presets (باقات حسب النشاط).
        Schema::create('sector_presets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon')->nullable();
            $table->string('engine')->nullable();
            $table->text('description_ar')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tenants (المشتركون).
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('sector_preset_id')->nullable()->constrained('sector_presets')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->default('OM');
            $table->string('currency', 8)->default('OMR');
            $table->string('logo')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->enum('subscription_status', ['trial', 'active', 'expired', 'suspended'])->default('trial');
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_renews_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Subscription periods history (monthly renewals).
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 10, 3)->default(0);
            $table->enum('status', ['paid', 'pending', 'cancelled'])->default('paid');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Module registry (سجل الوحدات) — the dynamic sidebar is built from this.
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('icon')->nullable();
            $table->string('route')->nullable();
            $table->string('parent_key')->nullable()->index();
            $table->string('engine')->default('core')->index();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_core')->default(false);   // always-on for every tenant
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });

        Schema::create('preset_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_preset_id')->constrained('sector_presets')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->unique(['sector_preset_id', 'module_id']);
        });

        // Users → tenant.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->boolean('is_owner')->default(false)->after('is_super_admin');
            $table->boolean('is_active')->default(true)->after('is_owner');
            $table->timestamp('last_login_at')->nullable();
        });

        // Roles & permissions (per tenant).
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name_ar');
            $table->string('group_key')->nullable()->index();  // module key for grouping
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name_ar');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['phone', 'is_super_admin', 'is_owner', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('preset_modules');
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('sector_presets');
        Schema::dropIfExists('plans');
    }
};
