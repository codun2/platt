<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'key', 'year', 'last'];

    /** Next document number for the current tenant, e.g. INV-2026-000012. Call inside a transaction. */
    public static function next(string $key, string $prefix): string
    {
        $year = (int) now()->format('Y');
        $row = static::firstOrCreate(['key' => $key, 'year' => $year], ['last' => 0]);
        $row = static::whereKey($row->id)->lockForUpdate()->first();
        $row->increment('last');

        return sprintf('%s-%d-%06d', $prefix, $year, $row->last);
    }
}
