<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduAttendance extends Model
{
    use BelongsToTenant;

    protected $table = 'edu_attendance';

    protected $fillable = ['tenant_id', 'student_id', 'class_id', 'date', 'status', 'note', 'user_id'];

    protected function casts(): array { return ['date' => 'date:Y-m-d']; }

    public function student(): BelongsTo { return $this->belongsTo(EduStudent::class, 'student_id'); }
}
