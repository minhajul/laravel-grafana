<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a value as a string and reads it back as the type named in the model's "cast" column.
 *
 * A cast class is used instead of a value() accessor, because a "value" method on the
 * model would shadow the query builder's value() for static calls like Datapoint::value('name').
 *
 * @implements CastsAttributes<string|int|float|bool|null, string|int|float|bool|null>
 */
final class TypedValue implements CastsAttributes
{
    /**
     * Convert the stored string to the type named in the "cast" column.
     *
     * Unknown cast types fall back to the raw string value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }

        return match ($attributes['cast'] ?? 'string') {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }

    /**
     * Store the value as a string, writing booleans as "true" or "false".
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
