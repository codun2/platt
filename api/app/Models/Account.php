<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToTenant;

    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    /** Natural (debit-positive) sides. Liability/equity/revenue grow on credit. */
    public const DEBIT_TYPES = ['asset', 'expense'];

    protected $fillable = ['tenant_id', 'code', 'name', 'type', 'parent_id', 'is_group', 'system_key', 'opening_balance', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_active' => 'boolean', 'opening_balance' => 'decimal:3'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, self::DEBIT_TYPES, true);
    }
}
