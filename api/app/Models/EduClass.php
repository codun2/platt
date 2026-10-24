<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduClass extends Model
{
    use BelongsToTenant;

    protected $table = 'edu_classes';

    protected $fillable = ['tenant_id', 'name', 'grade', 'section', 'stage', 'academic_year', 'teacher_id', 'room', 'capacity', 'annual_fee', 'is_active', 'sort'];

    protected function casts(): array { return ['annual_fee' => 'decimal:3', 'is_active' => 'boolean']; }

    public function students(): HasMany { return $this->hasMany(EduStudent::class, 'class_id'); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
