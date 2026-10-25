<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'type', 'points', 'amount', 'points_after', 'wallet_after', 'method',
        'ref_type', 'ref_id', 'reference', 'note', 'reversed', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:3', 'amount' => 'decimal:3', 'points_after' => 'decimal:3', 'wallet_after' => 'decimal:3',
            'reversed' => 'boolean',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
