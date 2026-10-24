<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduStudent extends Model
{
    use BelongsToTenant;

    protected $table = 'edu_students';

    protected $fillable = [
        'tenant_id', 'student_no', 'name', 'name_en', 'gender', 'birth_date', 'national_id', 'nationality', 'class_id', 'guardian_id',
        'guardian_relation', 'emergency_phone', 'enrolled_at', 'status', 'discount_pct', 'transport', 'medical_notes', 'notes',
    ];

    protected function casts(): array { return ['birth_date' => 'date:Y-m-d', 'enrolled_at' => 'date:Y-m-d', 'discount_pct' => 'decimal:2']; }

    public function eduClass(): BelongsTo { return $this->belongsTo(EduClass::class, 'class_id'); }
    public function guardian(): BelongsTo { return $this->belongsTo(Customer::class, 'guardian_id'); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class, 'student_id'); }
    public function attendance(): HasMany { return $this->hasMany(EduAttendance::class, 'student_id'); }
}
