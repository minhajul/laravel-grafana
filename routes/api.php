<?php

declare(strict_types=1);

use App\Http\Controllers\DatapointController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::apiResource('datapoints', DatapointController::class)->only(['index']);
});
