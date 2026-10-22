<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HR (core): employees, daily attendance, leaves, monthly payroll. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('employment_type', 20)->default('full_time');   // full_time | part_time | contract | daily
            $table->date('hire_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active');                // active | on_leave | terminated
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('iban', 60)->nullable();
            $table->decimal('basic_salary', 12, 3)->default(0);
            $table->decimal('housing_allowance', 12, 3)->default(0);
            $table->decimal('transport_allowance', 12, 3)->default(0);
            $table->decimal('other_allowance', 12, 3)->default(0);
            $table->decimal('fixed_deductions', 12, 3)->default(0);          // insurance etc.
            $table->unsignedSmallInteger('annual_leave_days')->default(30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('emergency_name', 150)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('present');   // present | absent | late | half_day | leave | holiday | weekend
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('annual');       // annual | sick | unpaid | emergency | maternity | other
            $table->date('from');
            $table->date('to');
            $table->unsignedSmallInteger('days')->default(1);
            $table->string('status', 20)->default('pending');    // pending | approved | rejected
            $table->string('reason', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('period', 7);                         // YYYY-MM
            $table->string('status', 20)->default('draft');      // draft | approved | paid
            $table->decimal('total_basic', 14, 3)->default(0);
            $table->decimal('total_allowances', 14, 3)->default(0);
            $table->decimal('total_additions', 14, 3)->default(0);
            $table->decimal('total_deductions', 14, 3)->default(0);
            $table->decimal('total_net', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('paid_from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'period']);
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->decimal('basic', 12, 3)->default(0);
            $table->decimal('allowances', 12, 3)->default(0);
            $table->decimal('overtime', 12, 3)->default(0);
            $table->decimal('bonus', 12, 3)->default(0);
            $table->decimal('absence_days', 5, 1)->default(0);
            $table->decimal('absence_deduction', 12, 3)->default(0);
            $table->decimal('fixed_deductions', 12, 3)->default(0);
            $table->decimal('other_deductions', 12, 3)->default(0);
            $table->decimal('advance', 12, 3)->default(0);
            $table->decimal('net', 12, 3)->default(0);
            $table->string('note', 255)->nullable();
            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        foreach (['payroll_items', 'payrolls', 'leaves', 'attendances', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
