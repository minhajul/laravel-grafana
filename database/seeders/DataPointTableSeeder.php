<?php

namespace Database\Seeders;

use App\Models\Datapoint;
use Illuminate\Database\Seeder;

class DataPointTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Datapoint::factory()
            ->times(10000)
            ->create();
    }
}
