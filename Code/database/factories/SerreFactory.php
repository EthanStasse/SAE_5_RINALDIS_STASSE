<?php

namespace Database\Factories;

use App\Models\Serre;
use App\Models\Structure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hors requête HTTP, créez les serres dans StructureCourante::sansFiltre()
 * ou après StructureCourante::definir().
 *
 * @extends Factory<Serre>
 */
class SerreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'structure_id' => Structure::factory(),
            'nom' => 'Serre '.fake()->unique()->numberBetween(1, 9999),
            'emplacement' => fake()->optional()->streetName(),
        ];
    }
}
