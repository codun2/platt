<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engine: Education — classes / sections, students (guardian = CRM customer),
 * daily attendance, and tuition billed as CRM invoices to the guardian
 * (invoices.student_id links each fee invoice to its student).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edu_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                    // "الصف الأول — أ"
            $table->string('grade', 60)->nullable();                   // "الصف الأول"
            $table->string('section', 20)->nullable();                 // "أ"
            $table->string('stage', 20)->nullable();                   // kindergarten, primary, middle, secondary
            $table->string('academic_year', 20)->nullable();           // "2026/2027"
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('room', 40)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->decimal('annual_fee', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('edu_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('student_no', 40)->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_id', 40)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->foreignId('class_id')->nullable()->constrained('edu_classes')->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('guardian_relation', 20)->nullable();       // father, mother, other
            $table->string('emergency_phone', 30)->nullable();
            $table->date('enrolled_at')->nullable();
            $table->string('status', 12)->default('active');           // active, withdrawn, graduated
            $table->decimal('discount_pct', 5, 2)->default(0);        // sibling / scholarship discount on fees
            $table->string('transport', 60)->nullable();               // bus route
            $table->text('medical_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'class_id', 'status']);
        });

        Schema::create('edu_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('edu_students')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('edu_classes')->nullOnDelete();
            $table->date('date');
            $table->string('status', 10);                              // present, absent, late, excused
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('customer_id')->constrained('edu_students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_id');
        });
        Schema::dropIfExists('edu_attendance');
        Schema::dropIfExists('edu_students');
        Schema::dropIfExists('edu_classes');
    }
};
