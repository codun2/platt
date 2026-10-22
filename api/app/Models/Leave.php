<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use BelongsToTenant;

    public const TYPES = ['annual', 'sick', 'unpaid', 'emergency', 'maternity', 'other'];

    protected $fillable = ['tenant_id', 'employee_id', 'type', 'from', 'to', 'days', 'status', 'reason', 'note', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['from' => 'date:Y-m-d', 'to' => 'date:Y-m-d', 'days' => 'integer', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
