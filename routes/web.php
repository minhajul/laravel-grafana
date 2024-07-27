<?php

use App\Http\Controllers\DatapointController;
use Illuminate\Support\Facades\Route;

Route::get('/', DatapointController::class)->name('datapoint.index');
