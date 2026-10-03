<?php

namespace Database\Factories;

use App\Enums\Emetteur;
use App\Enums\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Alerte>
 */
class AlerteFactory extends Factory
{
    /** Par défaut : une alerte d'information active depuis une heure, sans fin. */
    public function definition(): array
    {
        return [
            'titre' => fake()->sentence(4),
            'ce_qui_se_passe' => fake()->sentence(10),
            'ce_quil_faut_faire' => fake()->sentence(10),
            'secteur' => null,
            'niveau' => Niveau::Info,
            'emetteur' => Emetteur::Ville,
            'consignes_vulnerables' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => null,
            'user_id' => null,
        ];
    }

    public function niveau(Niveau $niveau): static
    {
        return $this->state(fn () => ['niveau' => $niveau]);
    }

    public function urgente(): static
    {
        return $this->niveau(Niveau::Urgent);
    }

    public function vigilance(): static
    {
        return $this->niveau(Niveau::Vigilance);
    }

    /** Pas encore commencée. */
    public function programmee(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
    }

    /** Déjà terminée. */
    public function terminee(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subDays(2), 'ends_at' => now()->subHour()]);
    }

    public function avecConsignesVulnerables(string $texte = 'Restez au frais et hydratez-vous.'): static
    {
        return $this->state(fn () => ['consignes_vulnerables' => $texte]);
    }
}
