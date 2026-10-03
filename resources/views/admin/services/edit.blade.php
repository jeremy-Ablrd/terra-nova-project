<x-app-layout :title="__('Modifier le service').' '.$service->nom">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Services'), 'url' => route('admin.services.index')],
            ['label' => $service->nom],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Modifier le service') }} : {{ $service->nom }}</h1>
    </x-slot>

    @php
        $champ = 'block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
        // Valeurs affichées : la saisie précédente si le formulaire est revenu en erreur, sinon l'état actuel du service.
        $dispo = old('disponibilite', $service->disponibilite->value);
        $retour = old('retour_estime_at', $retourSaisie);
    @endphp

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">{{ __('Le motif, le retour estimé et l\'alternative sont visibles du public tant que le service est interrompu ; ils sont effacés quand le service est remis en service.') }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ __('Les champs marqués d\'un * sont obligatoires.') }}</p>

                <form method="POST" action="{{ route('admin.services.update', $service) }}" class="mt-6 space-y-6" novalidate
                      x-data="{ envoi: false }" x-on:submit="envoi = true" x-on:pageshow.window="envoi = false">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="disponibilite" :value="__('Disponibilité').' *'" />
                        <select id="disponibilite" name="disponibilite" class="{{ $champ }}" required aria-required="true"
                                @if ($errors->has('disponibilite')) aria-invalid="true" aria-describedby="disponibilite_erreur" @endif>
                            @foreach ($disponibilites as $option)
                                <option value="{{ $option->value }}" @selected($dispo === $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error id="disponibilite_erreur" :messages="$errors->get('disponibilite')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="motif_interruption" :value="__('Motif de l\'interruption')" />
                        <textarea id="motif_interruption" name="motif_interruption" rows="3" maxlength="500" class="{{ $champ }}"
                                  aria-describedby="motif_aide{{ $errors->has('motif_interruption') ? ' motif_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('motif_interruption') ? 'true' : 'false' }}">{{ old('motif_interruption', $service->motif_interruption) }}</textarea>
                        <p id="motif_aide" class="mt-1 text-xs text-gray-500">{{ __('Obligatoire si le service est interrompu. 500 caractères maximum.') }}</p>
                        <x-input-error id="motif_erreur" :messages="$errors->get('motif_interruption')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="retour_estime_at" :value="__('Retour estimé')" />
                        <x-text-input id="retour_estime_at" name="retour_estime_at" class="block mt-1 w-full" type="datetime-local" :value="$retour"
                                      aria-describedby="retour_aide{{ $errors->has('retour_estime_at') ? ' retour_erreur' : '' }}" />
                        <p id="retour_aide" class="mt-1 text-xs text-gray-500">{{ __('Facultatif ; si renseigné, doit être dans le futur (heure de La Réunion).') }}</p>
                        <x-input-error id="retour_erreur" :messages="$errors->get('retour_estime_at')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="alternative" :value="__('Quoi faire à la place')" />
                        <textarea id="alternative" name="alternative" rows="3" maxlength="500" class="{{ $champ }}"
                                  aria-describedby="alternative_aide{{ $errors->has('alternative') ? ' alternative_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('alternative') ? 'true' : 'false' }}">{{ old('alternative', $service->alternative) }}</textarea>
                        <p id="alternative_aide" class="mt-1 text-xs text-gray-500">{{ __('Facultatif : une solution de remplacement pour les habitants. 500 caractères maximum.') }}</p>
                        <x-input-error id="alternative_erreur" :messages="$errors->get('alternative')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <label for="prioritaire" class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                            <input type="hidden" name="prioritaire" value="0">
                            <input id="prioritaire" type="checkbox" name="prioritaire" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                   @checked((bool) old('prioritaire', $service->prioritaire)) aria-describedby="prioritaire_aide">
                            {{ __('Service prioritaire') }}
                        </label>
                        <p id="prioritaire_aide" class="mt-1 text-xs text-gray-500">{{ __('Les services prioritaires sont mis en avant en tête du catalogue.') }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('admin.services.index') }}" class="text-sm underline text-gray-700 hover:text-gray-900">{{ __('Annuler') }}</a>
                        <x-primary-button x-bind:disabled="envoi" x-bind:aria-disabled="envoi" class="disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-text="envoi ? {{ Js::from(__('Enregistrement…')) }} : {{ Js::from(__('Enregistrer')) }}">{{ __('Enregistrer') }}</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
