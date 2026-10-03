<?php

namespace App\Http\Requests;

use App\Enums\Disponibilite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    /** Autorisation par la ServicePolicy (admin seul). */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('service'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'disponibilite' => ['required', Rule::enum(Disponibilite::class)],
            'prioritaire' => ['nullable', 'boolean'],
            // Le motif est obligatoire dès que le service est interrompu.
            'motif_interruption' => ['nullable', 'string', 'max:500', 'required_if:disponibilite,'.Disponibilite::Interrompu->value,
                Rule::requiredIf(fn () => $this->input('disponibilite') === Disponibilite::Desactive->value)],
            // Le retour estimé doit être dans le futur : date ET heure (une chaîne complète, car Rule::date()->after(Carbon)
            // ne garderait que le jour), par rapport à l'horloge de l'application.
            'retour_estime_at' => ['nullable', Rule::date()->after(now()->toDateTimeString())],
            'alternative' => ['nullable', 'string', 'max:500'],
            // Localisation (F46) : longueurs limitées ; téléphone = chiffres, espaces, points, tirets, parenthèses, « + » en tête.
            'adresse' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:100'],
            'repere' => ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'min:2', 'max:30', 'regex:/^(?=.*\d)\+?[0-9(][0-9 .()\-]*$/'],
            'urgence' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motif_interruption.required_if' => __('Indiquez le motif de l\'interruption.'),
            'motif_interruption.required' => __('Indiquez le motif de la désactivation.'),
            'telephone.regex' => __('Le téléphone ne peut contenir que des chiffres, des espaces, des points, des tirets, des parenthèses et un « + » au début.'),
            'retour_estime_at.after' => __('Le retour estimé doit être dans le futur.'),
        ];
    }
}
