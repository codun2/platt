<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'key', 'method', 'path', 'status', 'response', 'created_at'];
}
