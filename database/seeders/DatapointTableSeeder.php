<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Datapoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\LazyCollection;

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
        $factory = Datapoint::factory();
        $newest = now();
        $oldest = $newest->copy()->subDays(self::SPREAD_OVER_DAYS);

        LazyCollection::times(10000, function () use ($factory, $oldest, $newest): array {
            $createdAt = fake()->dateTimeBetween($oldest, $newest);

            return [...$factory->raw(), 'created_at' => $createdAt, 'updated_at' => $createdAt];
        })
            ->chunk(1000)
            ->each(fn (LazyCollection $chunk): bool => Datapoint::query()->insert($chunk->all()));
    }
}
