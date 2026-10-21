<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use BelongsToTenant;

    public const METHODS = ['cash', 'card', 'transfer', 'cheque'];

    protected $fillable = ['tenant_id', 'invoice_id', 'amount', 'method', 'date', 'reference', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'date' => 'date:Y-m-d'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
