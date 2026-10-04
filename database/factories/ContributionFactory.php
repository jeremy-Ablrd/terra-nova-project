<?php

namespace Database\Factories;

use App\Enums\StatutContribution;
use App\Enums\TypeContribution;
use App\Models\Projet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Par défaut : un avis sur un projet. La contribution est créée par `create()` : l'événement du modèle génère sa référence et sa première étape.
 *
 * @extends Factory<\App\Models\Contribution>
 */
class ContributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TypeContribution::Avis,
            'projet_id' => Projet::factory(),
            'service_id' => null,
            'titre' => null,
            'message' => fake()->paragraph(),
            'statut' => StatutContribution::Recue,
        ];
    }

    public function idee(): static
    {
        return $this->state(fn () => [
            'type' => TypeContribution::Idee,
            'projet_id' => null,
            'titre' => fake()->sentence(4),
        ]);
    }

    public function commentaire(): static
    {
        return $this->state(fn () => [
            'type' => TypeContribution::Commentaire,
            'projet_id' => null,
            'service_id' => Service::factory(),
        ]);
    }

    public function statut(StatutContribution $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }
}
