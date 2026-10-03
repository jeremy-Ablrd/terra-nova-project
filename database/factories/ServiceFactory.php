<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $nom = fake()->unique()->words(3, true);

        return [
            'nom' => Str::title($nom),
            'slug' => Str::slug($nom),
            'resume' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'horaires' => null,
            'lieu' => null,
            'contact' => null,
            'icone' => null,
            'ordre' => 0,
            'actif' => true,
        ];
    }
}
