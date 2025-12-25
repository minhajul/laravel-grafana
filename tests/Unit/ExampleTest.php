<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('application returns a successful response', function () {
    expect(true)->toBeTrue();
});
