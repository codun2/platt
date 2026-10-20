<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'sale_id', 'amount', 'method', 'date', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'date' => 'date:Y-m-d'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
