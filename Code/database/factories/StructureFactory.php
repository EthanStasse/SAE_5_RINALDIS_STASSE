<?php

namespace Database\Factories;

use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Structure>
 */
class StructureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom_commercial' => 'Jardin de '.fake()->unique()->city(),
            'email' => fake()->unique()->safeEmail(),
            'assujettie_tva' => fake()->boolean(),
            'actif' => true,
        ];
    }
}
