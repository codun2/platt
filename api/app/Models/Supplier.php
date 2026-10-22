<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'supplier_type', 'code', 'contact_person', 'phone', 'phone2', 'whatsapp', 'email',
        'tax_number', 'cr_number', 'address', 'country', 'city', 'area', 'bank_details', 'payment_terms_days',
        'category', 'opening_balance', 'tags', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'opening_balance' => 'decimal:3', 'payment_terms_days' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
