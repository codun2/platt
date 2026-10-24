<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'kind', 'title', 'customer_id', 'status', 'priority', 'lead_id', 'start_date', 'due_date', 'closed_at',
        'case_type', 'court', 'court_case_no', 'client_role', 'opponent', 'opponent_lawyer', 'stage',
        'location', 'contract_value', 'progress', 'fee_type', 'fee_amount', 'hourly_rate', 'description', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'closed_at' => 'date:Y-m-d',
            'contract_value' => 'decimal:3', 'fee_amount' => 'decimal:3', 'hourly_rate' => 'decimal:3', 'progress' => 'integer',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lead(): BelongsTo { return $this->belongsTo(User::class, 'lead_id'); }
    public function tasks(): HasMany { return $this->hasMany(ProjectTask::class); }
    public function timesheets(): HasMany { return $this->hasMany(Timesheet::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
