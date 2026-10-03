<?php

namespace App\Http\Requests;

use App\Enums\Disponibilite;
use App\Models\Service;
use Illuminate\Contracts\Validation\Validator;
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
            // F74 : organisme et horaires d'ouverture (7 jours, 2 plages au plus par jour, heures HH:MM).
            'organisme' => ['nullable', 'string', 'max:100'],
            'horaires' => ['nullable', 'array'],
            'horaires.*' => ['nullable', 'array', 'max:2'],
            'horaires.*.*' => ['nullable', 'array'],
            'horaires.*.*.ouverture' => ['nullable', 'date_format:H:i'],
            'horaires.*.*.fermeture' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Horaires : chaque plage est complète, la fermeture vient après l'ouverture (une plage ne passe jamais minuit),
     * et la deuxième plage commence après la fin de la première.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validateur) {
            foreach ((array) $this->input('horaires', []) as $jour => $plages) {
                if (! in_array($jour, Service::JOURS, true)) {
                    $validateur->errors()->add('horaires', __('Jour inconnu dans les horaires.'));

                    continue;
                }
                $nom = __('ouverture_service.jours.'.$jour);
                $valides = [];

                foreach (array_values((array) $plages) as $rang => $plage) {
                    $ouverture = $plage['ouverture'] ?? null;
                    $fermeture = $plage['fermeture'] ?? null;
                    if (blank($ouverture) && blank($fermeture)) {
                        continue;
                    }
                    if (blank($ouverture) || blank($fermeture)) {
                        $validateur->errors()->add("horaires.{$jour}", __('Le :jour : renseignez l\'ouverture et la fermeture de la plage :n, ou videz les deux.', ['jour' => $nom, 'n' => $rang + 1]));
                    } elseif ($fermeture <= $ouverture) {
                        $validateur->errors()->add("horaires.{$jour}", __('Le :jour : la fermeture de la plage :n doit être après son ouverture (une plage ne peut pas passer minuit).', ['jour' => $nom, 'n' => $rang + 1]));
                    } else {
                        $valides[] = [$ouverture, $fermeture];
                    }
                }

                // Les plages sont triées à l'enregistrement : l'ordre de saisie n'importe pas, mais elles ne doivent pas se chevaucher.
                usort($valides, fn ($a, $b) => strcmp($a[0], $b[0]));
                if (count($valides) === 2 && $valides[1][0] < $valides[0][1]) {
                    $validateur->errors()->add("horaires.{$jour}", __('Le :jour : les deux plages ne doivent pas se chevaucher : la seconde doit commencer après la fin de la première.', ['jour' => $nom]));
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motif_interruption.required_if' => __('Indiquez le motif de l\'interruption.'),
            'motif_interruption.required' => __('Indiquez le motif de la désactivation.'),
            'telephone.regex' => __('Le téléphone ne peut contenir que des chiffres, des espaces, des points, des tirets, des parenthèses et un « + » au début.'),
            'horaires.*.*.ouverture.date_format' => __('Heure d\'ouverture invalide : utilisez le format HH:MM.'),
            'horaires.*.*.fermeture.date_format' => __('Heure de fermeture invalide : utilisez le format HH:MM.'),
            'retour_estime_at.after' => __('Le retour estimé doit être dans le futur.'),
        ];
    }
}
