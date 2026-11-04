<?php

declare(strict_types=1);

use App\Models\Datapoint;

it('converts the stored value to the type named in the cast column', function (string $cast, string $storedValue, string|int|float|bool $expectedValue) {
    $datapoint = new Datapoint(['value' => $storedValue, 'cast' => $cast]);

    expect($datapoint->value)->toBe($expectedValue);
})->with([
    'integer' => ['integer', '42', 42],
    'int alias' => ['int', '42', 42],
    'float' => ['float', '36.6', 36.6],
    'double alias' => ['double', '36.6', 36.6],
    'boolean true' => ['boolean', 'true', true],
    'boolean false' => ['boolean', '0', false],
    'bool alias' => ['bool', '1', true],
    'string' => ['string', '36.6', '36.6'],
]);

it('falls back to the string value for an unknown cast type', function () {
    $datapoint = new Datapoint(['value' => '42', 'cast' => 'decimal']);

    expect($datapoint->value)->toBe('42');
});

it('treats the value as a string when no cast is set', function () {
    $datapoint = new Datapoint(['value' => '42']);

    expect($datapoint->value)->toBe('42');
});

it('returns null when no value is set', function () {
    $datapoint = new Datapoint(['cast' => 'float']);

    expect($datapoint->value)->toBeNull();
});
