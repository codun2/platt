<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'warehouse_id', 'opened_at', 'closed_at', 'opening_cash', 'expected_cash', 'closing_cash', 'status', 'note'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'closed_at' => 'datetime',
            'opening_cash' => 'decimal:3', 'expected_cash' => 'decimal:3', 'closing_cash' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
