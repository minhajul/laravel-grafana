<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DatapointResource;
use App\Models\Datapoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DatapointController extends Controller
{
    private const int DEFAULT_PER_PAGE = 20;

    private const int MAX_PER_PAGE = 100;

    /**
     * List datapoints in insertion order, paginated.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $datapoints = Datapoint::query()
            ->orderBy('id')
            ->paginate($request->integer('per_page', self::DEFAULT_PER_PAGE))
            ->withQueryString();

        return DatapointResource::collection($datapoints);
    }
}
