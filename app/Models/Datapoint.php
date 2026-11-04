<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\TypedValue;
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => TypedValue::class,
        ];
    }
}
