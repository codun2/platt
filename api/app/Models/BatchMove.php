<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchMove extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'qty', 'type', 'ref_type', 'ref_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }
}
