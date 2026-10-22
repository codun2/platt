<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['payroll_id', 'employee_id', 'basic', 'allowances', 'overtime', 'bonus', 'absence_days', 'absence_deduction', 'fixed_deductions', 'other_deductions', 'advance', 'net', 'note'];

    protected function casts(): array
    {
        return ['basic' => 'decimal:3', 'allowances' => 'decimal:3', 'overtime' => 'decimal:3', 'bonus' => 'decimal:3', 'absence_days' => 'decimal:1', 'absence_deduction' => 'decimal:3', 'fixed_deductions' => 'decimal:3', 'other_deductions' => 'decimal:3', 'advance' => 'decimal:3', 'net' => 'decimal:3'];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function computeNet(): float
    {
        return round((float) $this->basic + (float) $this->allowances + (float) $this->overtime + (float) $this->bonus
            - (float) $this->absence_deduction - (float) $this->fixed_deductions - (float) $this->other_deductions - (float) $this->advance, 3);
    }
}
