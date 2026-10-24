<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine: Projects — legal cases (law firms) and engineering / consulting
 * projects. A project has tasks (hearings for cases), and time entries that
 * can be billed to the client as a draft CRM invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('kind', 10)->default('project');            // project, case
            $table->string('title');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // open, in_progress, on_hold, closed (+ for cases: won, lost, settled)
            $table->string('status', 15)->default('open');
            $table->string('priority', 10)->default('normal');         // low, normal, high, urgent
            $table->foreignId('lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('closed_at')->nullable();
            // legal case
            $table->string('case_type', 20)->nullable();               // civil, commercial, criminal, labor, family, administrative, real_estate, other
            $table->string('court')->nullable();
            $table->string('court_case_no', 60)->nullable();
            $table->string('client_role', 15)->nullable();             // plaintiff, defendant, other
            $table->string('opponent')->nullable();
            $table->string('opponent_lawyer')->nullable();
            $table->string('stage', 20)->nullable();                   // first_instance, appeal, cassation, execution, consultation
            // project
            $table->string('location')->nullable();
            $table->decimal('contract_value', 14, 3)->default(0);
            $table->unsignedTinyInteger('progress')->default(0);       // %
            // fees
            $table->string('fee_type', 10)->default('hourly');         // hourly, fixed, retainer
            $table->decimal('fee_amount', 14, 3)->default(0);          // fixed / retainer amount
            $table->decimal('hourly_rate', 12, 3)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'kind', 'status']);
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('kind', 10)->default('task');               // task, hearing
            $table->string('title');
            $table->dateTime('due_at')->nullable();
            $table->string('location')->nullable();                    // court hall / site
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('todo');             // todo, doing, done, postponed, cancelled
            $table->string('priority', 10)->default('normal');
            $table->text('notes')->nullable();
            $table->text('outcome')->nullable();                       // hearing result
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'due_at']);
        });

        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->decimal('hours', 6, 2);
            $table->string('description')->nullable();
            $table->boolean('billable')->default(true);
            $table->decimal('rate', 12, 3)->default(0);
            $table->decimal('amount', 14, 3)->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
    }
};
