<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'project_id', 'task_id', 'user_id', 'date', 'hours', 'description', 'billable', 'rate', 'amount', 'invoice_id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'hours' => 'decimal:2', 'rate' => 'decimal:3', 'amount' => 'decimal:3', 'billable' => 'boolean'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function task(): BelongsTo { return $this->belongsTo(ProjectTask::class, 'task_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
