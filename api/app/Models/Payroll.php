<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'number', 'period', 'status', 'total_basic', 'total_allowances', 'total_additions', 'total_deductions', 'total_net', 'notes', 'paid_from_account_id', 'journal_entry_id', 'created_by', 'approved_at', 'paid_at'];

    protected function casts(): array
    {
        return ['total_basic' => 'decimal:3', 'total_allowances' => 'decimal:3', 'total_additions' => 'decimal:3', 'total_deductions' => 'decimal:3', 'total_net' => 'decimal:3', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    /** Recompute header totals from items. */
    public function recalc(): void
    {
        $i = $this->items()->get();
        $this->update([
            'total_basic' => $i->sum('basic'), 'total_allowances' => $i->sum('allowances'),
            'total_additions' => $i->sum('overtime') + $i->sum('bonus'),
            'total_deductions' => $i->sum('absence_deduction') + $i->sum('fixed_deductions') + $i->sum('other_deductions') + $i->sum('advance'),
            'total_net' => $i->sum('net'),
        ]);
    }
}
