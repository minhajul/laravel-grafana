<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Datapoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class DatapointTableSeeder extends Seeder
{
    /**
     * How far back the seeded datapoints' timestamps are spread.
     */
    private const int SPREAD_OVER_DAYS = 7;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now()->toImmutable();

        Datapoint::factory()
            ->times(10000)
            ->make()
            ->map(function (Datapoint $datapoint) use ($now): array {
                $createdAt = $now->subSeconds(fake()->numberBetween(0, self::SPREAD_OVER_DAYS * 86400));

                return [
                    ...$datapoint->getAttributes(),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            })
            ->chunk(1000)
            ->each(fn (Collection $chunk): bool => Datapoint::query()->insert($chunk->all()));
    }
}
