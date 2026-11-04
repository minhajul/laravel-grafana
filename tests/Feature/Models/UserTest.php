<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

it('hashes a plain text password when it is set', function () {
    $user = User::factory()->create(['password' => 'plain-text-password']);

    expect($user->password)->not->toBe('plain-text-password')
        ->and(Hash::check('plain-text-password', $user->password))->toBeTrue();
});

it('casts the email verification timestamp to a date', function () {
    $user = User::factory()->create(['email_verified_at' => '2025-01-01 12:00:00']);

    expect($user->fresh()->email_verified_at)->toBeInstanceOf(CarbonInterface::class);
});
