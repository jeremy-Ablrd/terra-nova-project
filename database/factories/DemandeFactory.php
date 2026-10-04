<?php

namespace Database\Factories;

use App\Enums\Priorite;
use App\Enums\Statut;
use App\Enums\TypeDemande;
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
            'priorite' => Priorite::Normale,
        ];
    }

    public function priorite(Priorite $priorite): static
    {
        return $this->state(fn () => ['priorite' => $priorite]);
    }

    /** Demande importée de l'API : sans compte utilisateur. */
    public function importee(?string $code = null, string $nom = 'Citoyenne anonyme'): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'request_code' => $code ?? 'X'.fake()->unique()->numerify('####'),
            'demandeur_nom' => $nom,
        ]);
    }

    public function type(TypeDemande $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function statut(Statut $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }
}
