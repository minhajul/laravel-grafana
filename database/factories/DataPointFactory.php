<?php

namespace Database\Factories;

use App\Models\Datapoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataPointFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Datapoint::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => "temperature",
            'value' => $this->faker->randomFloat(0, 90),
            'cast' => "float",
        ];
    }
}
