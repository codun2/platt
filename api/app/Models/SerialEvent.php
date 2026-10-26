<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerialEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['serial_unit_id', 'type', 'from_status', 'to_status', 'ref', 'note', 'user_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
