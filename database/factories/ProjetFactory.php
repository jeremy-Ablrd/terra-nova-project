<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Projet>
 */
class ProjetFactory extends Factory
{
    public function definition(): array
    {
        $titre = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($titre).'-'.fake()->unique()->numerify('###'),
            'titre' => Str::title($titre),
            'resume' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'publie_at' => now()->subDay(),
            'consultation_debut_at' => now()->subDays(2),
            'consultation_fin_at' => now()->addDays(10),
        ];
    }

    public function brouillon(): static
    {
        return $this->state(fn () => ['publie_at' => null]);
    }

    public function sansConsultation(): static
    {
        return $this->state(fn () => ['consultation_debut_at' => null, 'consultation_fin_at' => null]);
    }

    public function aVenir(): static
    {
        return $this->state(fn () => ['consultation_debut_at' => now()->addDays(3), 'consultation_fin_at' => now()->addDays(20)]);
    }

    public function close(): static
    {
        return $this->state(fn () => ['consultation_debut_at' => now()->subDays(30), 'consultation_fin_at' => now()->subDays(2)]);
    }
}
