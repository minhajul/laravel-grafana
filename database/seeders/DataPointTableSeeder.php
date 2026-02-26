<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Datapoint;
use Illuminate\Database\Seeder;

final class DataPointTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = Datapoint::factory()
            ->times(10000)
            ->make()
            ->map(fn($model) => $model->getAttributes())
            ->toArray();

        collect($data)->chunk(1000)->each(fn($chunk) => Datapoint::query()->insert($chunk->toArray()));
    }
}
