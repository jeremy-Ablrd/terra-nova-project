<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // null = « Je ne sais pas » : la mairie oriente la demande.
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('actif', true),
                // Coupure d'urgence (F63) : un service désactivé ne peut plus être choisi.
                function (string $attribut, mixed $valeur, \Closure $echec) {
                    $service = $valeur === null ? null : Service::find($valeur);
                    if ($service?->estDesactive()) {
                        $echec(__('Le service « :nom » est désactivé pour le moment : choisissez un autre service ou « Je ne sais pas ».', ['nom' => $service->nom]));
                    }
                }],
            'objet' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            // F86 : la seule priorité que l'habitant fixe lui-même ; le reste est décidé par les agents.
            'urgence_medicale' => ['nullable', 'boolean'],
        ];
    }
}
