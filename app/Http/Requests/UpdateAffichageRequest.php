<?php

namespace App\Http\Requests;

use App\Enums\TailleTexte;
use App\Enums\ThemeAffichage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Réglage d'affichage : chaque bouton du formulaire envoie UNE valeur (taille ou thème). Ouvert à tous les visiteurs. */
class UpdateAffichageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'taille' => ['nullable', Rule::enum(TailleTexte::class)],
            'theme' => ['nullable', Rule::enum(ThemeAffichage::class)],
        ];
    }
}
