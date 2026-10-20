<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_id', 'service_id', 'name', 'qty', 'price', 'total'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:2', 'price' => 'decimal:3', 'total' => 'decimal:3'];
    }
}
