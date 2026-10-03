<?php

namespace Database\Factories;

use App\Enums\Statut;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Demande>
 */
class DemandeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'objet' => fake()->sentence(4),
            'message' => fake()->paragraph(),
        ];
    }

    public function statut(Statut $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }
}
