<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTask extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'project_id', 'kind', 'title', 'due_at', 'location', 'assigned_to', 'status', 'priority', 'notes', 'outcome', 'done_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime:Y-m-d H:i', 'done_at' => 'datetime:Y-m-d H:i'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
