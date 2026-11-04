<?php

declare(strict_types=1);

use App\Models\Datapoint;

it('keeps the query builder value() method available for static calls', function () {
    Datapoint::factory()->create(['name' => 'humidity']);

    expect(Datapoint::value('name'))->toBe('humidity');
});

it('stores the value as a string and reads it back typed by the cast column', function (string $cast, string|int|float|bool $assignedValue, string $storedValue, string|int|float|bool $readValue) {
    $datapoint = Datapoint::factory()->create(['cast' => $cast, 'value' => $assignedValue]);

    expect($datapoint->getRawOriginal('value'))->toBe($storedValue)
        ->and($datapoint->fresh()->value)->toBe($readValue);
})->with([
    'float' => ['float', 36.6, '36.6', 36.6],
    'integer' => ['integer', 42, '42', 42],
    'boolean true' => ['boolean', true, 'true', true],
    'boolean false' => ['boolean', false, 'false', false],
    'string' => ['string', 'on', 'on', 'on'],
]);
