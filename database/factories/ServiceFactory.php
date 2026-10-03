<?php

namespace Database\Factories;

use App\Enums\CategorieService;
use App\Enums\Disponibilite;
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
            'categorie' => CategorieService::Autre,
            'prioritaire' => false,
            'disponibilite' => Disponibilite::Disponible,
            'motif_interruption' => null,
            'retour_estime_at' => null,
            'alternative' => null,
            'ordre' => 0,
            'actif' => true,
        ];
    }

    public function categorie(CategorieService $categorie): static
    {
        return $this->state(fn () => ['categorie' => $categorie]);
    }

    public function prioritaire(): static
    {
        return $this->state(fn () => ['prioritaire' => true]);
    }

    /** Service interrompu, avec un retour estimé dans un jour et une alternative (relatifs à maintenant). */
    public function interrompu(?string $motif = 'Maintenance du service.', ?string $alternative = 'Passez par le guichet du centre-ville.'): static
    {
        return $this->state(fn () => [
            'disponibilite' => Disponibilite::Interrompu,
            'motif_interruption' => $motif,
            'retour_estime_at' => now()->addDay(),
            'alternative' => $alternative,
        ]);
    }
}
