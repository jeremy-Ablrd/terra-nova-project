<?php

namespace App\Http\Requests;

use App\Enums\Emetteur;
use App\Enums\Niveau;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlerteRequest extends FormRequest
{
    /** L'accès est réservé à l'admin par le middleware role:admin du groupe de routes. */
    public function authorize(): bool
    {
        return true;
    }

    /** Début vide = publication immédiate ; la règle « fin après début » a alors toujours une date de comparaison. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'starts_at' => $this->input('starts_at') ?: now()->toDateTimeString(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:150'],
            'ce_qui_se_passe' => ['required', 'string', 'max:1000'],
            'ce_quil_faut_faire' => ['required', 'string', 'max:1000'],
            'secteur' => ['nullable', 'string', 'max:100'],
            'niveau' => ['required', Rule::enum(Niveau::class)],
            'emetteur' => ['nullable', Rule::enum(Emetteur::class)],
            'consignes_vulnerables' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
