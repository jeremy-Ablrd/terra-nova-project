<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Coupure d'urgence d'un service (F63) : motif obligatoire, alternative facultative. Autorisation : ServicePolicy (admin seul). */
class DesactiverServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('service'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'motif_interruption' => ['required', 'string', 'max:500'],
            'alternative' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'motif_interruption.required' => __('Indiquez le motif de la désactivation.'),
        ];
    }
}
