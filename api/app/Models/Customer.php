<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'customer_type', 'code', 'phone', 'phone2', 'whatsapp',
        'email', 'tax_number', 'id_type', 'id_number', 'nationality', 'birth_date', 'gender',
        'company_name', 'contact_person', 'address', 'country', 'city', 'area',
        'category', 'credit_limit', 'opening_balance', 'payment_terms_days', 'tags',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'birth_date' => 'date:Y-m-d',
            'credit_limit' => 'decimal:3',
            'opening_balance' => 'decimal:3',
            'payment_terms_days' => 'integer',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
