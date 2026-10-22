<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'number', 'date', 'reference', 'description', 'status', 'source_type', 'source_id', 'total', 'created_by', 'posted_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'posted_at' => 'datetime', 'total' => 'decimal:3'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isManual(): bool
    {
        return $this->source_type === null || $this->source_type === 'manual';
    }
}
