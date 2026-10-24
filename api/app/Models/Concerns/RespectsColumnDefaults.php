<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net: a form field left empty arrives as NULL (ConvertEmptyStringsToNull).
 * For a NOT NULL column that would be a 500 "Integrity constraint violation".
 * Instead: on insert the attribute is dropped so the column default applies;
 * on update it takes the column default (or keeps its previous value).
 */
trait RespectsColumnDefaults
{
    /** @var array<string, array<string, array{nullable: bool, default: mixed}>> */
    private static array $columnMeta = [];

    protected static function bootRespectsColumnDefaults(): void
    {
        static::saving(function (Model $m) {
            $meta = self::columnMetaFor($m->getTable());
            foreach ($m->getAttributes() as $key => $value) {
                if ($value !== null || ! isset($meta[$key]) || $meta[$key]['nullable'] || $key === $m->getKeyName()) {
                    continue;
                }
                $default = $meta[$key]['default'];
                if (! $m->exists) {
                    if ($default !== null) {
                        $m->offsetUnset($key);   // let the database default apply
                    }
                } elseif ($m->isDirty($key)) {
                    $m->setAttribute($key, $default ?? $m->getOriginal($key));
                }
            }
        });
    }

    private static function columnMetaFor(string $table): array
    {
        if (! isset(self::$columnMeta[$table])) {
            $cols = [];
            foreach (Schema::getColumns($table) as $c) {
                $d = $c['default'];
                if (is_string($d)) {
                    $d = trim($d, "'");
                    if (strtoupper($d) === 'NULL' || str_contains(strtoupper($d), 'CURRENT_TIMESTAMP')) {
                        $d = null;
                    }
                }
                $cols[$c['name']] = ['nullable' => (bool) $c['nullable'], 'default' => $d];
            }
            self::$columnMeta[$table] = $cols;
        }

        return self::$columnMeta[$table];
    }
}
