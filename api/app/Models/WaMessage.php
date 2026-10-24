<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'template_id', 'customer_id', 'recipient', 'phone', 'body', 'context', 'user_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function template(): BelongsTo { return $this->belongsTo(WaTemplate::class, 'template_id'); }
}
