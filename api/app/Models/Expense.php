<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToTenant;

    public const METHODS = ['cash', 'card', 'transfer', 'cheque'];

    protected $fillable = ['tenant_id', 'number', 'date', 'account_id', 'paid_from_account_id', 'supplier_id', 'payee', 'amount', 'tax', 'total', 'payment_method', 'reference', 'description', 'notes', 'journal_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'amount' => 'decimal:3', 'tax' => 'decimal:3', 'total' => 'decimal:3'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
