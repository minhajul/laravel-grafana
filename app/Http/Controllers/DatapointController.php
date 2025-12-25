<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Datapoint;

final class DatapointController extends Controller
{
    public function __invoke()
    {
        $dataPoints = Datapoint::query()->paginate(20);

        return response()->json([
            'status' => 'OK',
            'data' => $dataPoints->items(),
            'meta' => [
                'total' => $dataPoints->total(),
                'per_page' => $dataPoints->perPage(),
                'current_page' => $dataPoints->currentPage(),
                'last_page' => $dataPoints->lastPage(),
                'next_page_url' => $dataPoints->nextPageUrl(),
                'previous_page_url' => $dataPoints->previousPageUrl(),
            ],
        ]);
    }
}
