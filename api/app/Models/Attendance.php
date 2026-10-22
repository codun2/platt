<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave', 'holiday', 'weekend'];

    protected $fillable = ['tenant_id', 'employee_id', 'date', 'status', 'check_in', 'check_out', 'late_minutes', 'overtime_minutes', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'late_minutes' => 'integer', 'overtime_minutes' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
