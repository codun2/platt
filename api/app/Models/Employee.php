<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'name_en', 'job_title', 'department', 'employment_type', 'hire_date', 'end_date', 'status',
        'phone', 'email', 'national_id', 'nationality', 'birth_date', 'gender', 'marital_status', 'address', 'bank_name', 'iban',
        'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions', 'annual_leave_days',
        'user_id', 'emergency_name', 'emergency_phone', 'notes',
    ];

    protected $appends = ['gross_salary'];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'birth_date' => 'date:Y-m-d',
            'basic_salary' => 'decimal:3', 'housing_allowance' => 'decimal:3', 'transport_allowance' => 'decimal:3', 'other_allowance' => 'decimal:3', 'fixed_deductions' => 'decimal:3',
            'annual_leave_days' => 'integer',
        ];
    }

    public function getGrossSalaryAttribute(): float
    {
        return round((float) $this->basic_salary + (float) $this->housing_allowance + (float) $this->transport_allowance + (float) $this->other_allowance, 3);
    }

    public function allowancesTotal(): float
    {
        return round((float) $this->housing_allowance + (float) $this->transport_allowance + (float) $this->other_allowance, 3);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
