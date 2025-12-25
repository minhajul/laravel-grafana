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
        Datapoint::factory()
            ->times(10000)
            ->create();
    }
}
