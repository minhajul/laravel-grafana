<?php

namespace App\Http\Controllers;

use App\Models\Datapoint;

class DatapointController extends Controller
{
    public function __invoke()
    {
        return Datapoint::query()->paginate(20);
    }
}
