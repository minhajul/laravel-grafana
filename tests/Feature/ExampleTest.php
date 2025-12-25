<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('application returns a successful response', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});
