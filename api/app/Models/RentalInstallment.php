<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalInstallment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'contract_id', 'seq', 'due_date', 'period_end', 'amount', 'paid', 'status', 'paid_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'paid_at' => 'date:Y-m-d', 'amount' => 'decimal:3', 'paid' => 'decimal:3'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RentalContract::class, 'contract_id');
    }
}
