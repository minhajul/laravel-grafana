<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Datapoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'cast',
    ];

    /**
     * Get the stored value converted to the type named in the "cast" column.
     *
     * Unknown cast types fall back to the raw string value.
     *
     * @return Attribute<string|int|float|bool|null, never>
     */
    protected function value(): Attribute
    {
        return Attribute::get(function (mixed $value, array $attributes): string|int|float|bool|null {
            if ($value === null) {
                return null;
            }

            return match ($attributes['cast'] ?? 'string') {
                'int', 'integer' => (int) $value,
                'float', 'double' => (float) $value,
                'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => (string) $value,
            };
        });
    }
}
