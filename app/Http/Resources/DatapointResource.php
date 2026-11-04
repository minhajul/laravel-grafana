<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Datapoint;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Datapoint
 */
final class DatapointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, value: string|int|float|bool|null, cast: string, created_at: ?CarbonInterface, updated_at: ?CarbonInterface}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'value' => $this->value,
            'cast' => $this->cast,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
