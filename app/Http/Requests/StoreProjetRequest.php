<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Création et modification d'un projet. L'accès est réservé à l'admin par le middleware role:admin du groupe de routes et ProjetPolicy. */
class StoreProjetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:150'],
            'resume' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'consultation_debut_at' => ['nullable', 'date'],
            'consultation_fin_at' => ['nullable', 'date', 'after:consultation_debut_at'],
            'bilan' => ['nullable', 'string', 'max:2000'],
            'publie' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'titre' => __('titre'),
            'resume' => __('résumé'),
            'description' => __('description'),
            'consultation_debut_at' => __('début de la consultation'),
            'consultation_fin_at' => __('fin de la consultation'),
            'bilan' => __('bilan'),
        ];
    }
}
