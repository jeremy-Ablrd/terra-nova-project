<x-app-layout :title="__('Nouvelle alerte')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Alertes'), 'url' => route('admin.alertes.index')],
            ['label' => __('Nouvelle alerte')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Nouvelle alerte') }}</h1>
    </x-slot>

    @php
        $champ = 'block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
    @endphp

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">{{ __('L\'alerte est visible de tous les visiteurs, connectés ou non, pendant sa période de diffusion.') }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ __('Les champs marqués d\'un * sont obligatoires.') }}</p>

                <form method="POST" action="{{ route('admin.alertes.store') }}" class="mt-6 space-y-6" novalidate
                      x-data="{ envoi: false }" x-on:submit="envoi = true" x-on:pageshow.window="envoi = false">
                    @csrf

                    <div>
                        <x-input-label for="titre" :value="__('Titre').' *'" />
                        <x-text-input id="titre" name="titre" class="block mt-1 w-full" type="text" :value="old('titre')" maxlength="150" required aria-required="true"
                                      aria-describedby="titre_aide{{ $errors->has('titre') ? ' titre_erreur' : '' }}" :aria-invalid="$errors->has('titre') ? 'true' : 'false'" />
                        <p id="titre_aide" class="mt-1 text-xs text-gray-500">{{ __('150 caractères maximum.') }}</p>
                        <x-input-error id="titre_erreur" :messages="$errors->get('titre')" class="mt-2" role="alert" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="niveau" :value="__('Niveau').' *'" />
                            <select id="niveau" name="niveau" class="{{ $champ }}" required aria-required="true"
                                    @if ($errors->has('niveau')) aria-invalid="true" aria-describedby="niveau_erreur" @endif>
                                @foreach ($niveaux as $niveau)
                                    <option value="{{ $niveau->value }}" @selected(old('niveau', 'info') === $niveau->value)>{{ $niveau->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error id="niveau_erreur" :messages="$errors->get('niveau')" class="mt-2" role="alert" />
                        </div>
                        <div>
                            <x-input-label for="secteur" :value="__('Secteur')" />
                            <x-text-input id="secteur" name="secteur" class="block mt-1 w-full" type="text" :value="old('secteur')" maxlength="100"
                                          aria-describedby="secteur_aide{{ $errors->has('secteur') ? ' secteur_erreur' : '' }}" />
                            <p id="secteur_aide" class="mt-1 text-xs text-gray-500">{{ __('Facultatif, par exemple « Quartier sud ».') }}</p>
                            <x-input-error id="secteur_erreur" :messages="$errors->get('secteur')" class="mt-2" role="alert" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="ce_qui_se_passe" :value="__('Ce qui se passe').' *'" />
                        <textarea id="ce_qui_se_passe" name="ce_qui_se_passe" rows="4" maxlength="1000" required aria-required="true" class="{{ $champ }}"
                                  aria-describedby="ce_qui_se_passe_aide{{ $errors->has('ce_qui_se_passe') ? ' ce_qui_se_passe_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('ce_qui_se_passe') ? 'true' : 'false' }}">{{ old('ce_qui_se_passe') }}</textarea>
                        <p id="ce_qui_se_passe_aide" class="mt-1 text-xs text-gray-500">{{ __('1 000 caractères maximum.') }}</p>
                        <x-input-error id="ce_qui_se_passe_erreur" :messages="$errors->get('ce_qui_se_passe')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="ce_quil_faut_faire" :value="__('Ce qu\'il faut faire').' *'" />
                        <textarea id="ce_quil_faut_faire" name="ce_quil_faut_faire" rows="4" maxlength="1000" required aria-required="true" class="{{ $champ }}"
                                  aria-describedby="ce_quil_faut_faire_aide{{ $errors->has('ce_quil_faut_faire') ? ' ce_quil_faut_faire_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('ce_quil_faut_faire') ? 'true' : 'false' }}">{{ old('ce_quil_faut_faire') }}</textarea>
                        <p id="ce_quil_faut_faire_aide" class="mt-1 text-xs text-gray-500">{{ __('1 000 caractères maximum.') }}</p>
                        <x-input-error id="ce_quil_faut_faire_erreur" :messages="$errors->get('ce_quil_faut_faire')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="consignes_vulnerables" :value="__('Consignes pour les personnes vulnérables')" />
                        <textarea id="consignes_vulnerables" name="consignes_vulnerables" rows="3" maxlength="1000" class="{{ $champ }}"
                                  aria-describedby="consignes_vulnerables_aide{{ $errors->has('consignes_vulnerables') ? ' consignes_vulnerables_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('consignes_vulnerables') ? 'true' : 'false' }}">{{ old('consignes_vulnerables') }}</textarea>
                        <p id="consignes_vulnerables_aide" class="mt-1 text-xs text-gray-500">{{ __('Facultatif. Affichées sur la page de détail de l\'alerte.') }}</p>
                        <x-input-error id="consignes_vulnerables_erreur" :messages="$errors->get('consignes_vulnerables')" class="mt-2" role="alert" />
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="starts_at" :value="__('Début de la diffusion')" />
                            <x-text-input id="starts_at" name="starts_at" class="block mt-1 w-full" type="datetime-local" :value="old('starts_at')"
                                          aria-describedby="starts_at_aide{{ $errors->has('starts_at') ? ' starts_at_erreur' : '' }}" />
                            <p id="starts_at_aide" class="mt-1 text-xs text-gray-500">{{ __('Laissez vide pour publier maintenant (heure de La Réunion).') }}</p>
                            <x-input-error id="starts_at_erreur" :messages="$errors->get('starts_at')" class="mt-2" role="alert" />
                        </div>
                        <div>
                            <x-input-label for="ends_at" :value="__('Fin de la diffusion')" />
                            <x-text-input id="ends_at" name="ends_at" class="block mt-1 w-full" type="datetime-local" :value="old('ends_at')"
                                          aria-describedby="ends_at_aide{{ $errors->has('ends_at') ? ' ends_at_erreur' : '' }}" />
                            <p id="ends_at_aide" class="mt-1 text-xs text-gray-500">{{ __('Facultatif : sans date, l\'alerte reste affichée jusqu\'à « Terminer maintenant ».') }}</p>
                            <x-input-error id="ends_at_erreur" :messages="$errors->get('ends_at')" class="mt-2" role="alert" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('admin.alertes.index') }}" class="text-sm underline text-gray-700 hover:text-gray-900">{{ __('Annuler') }}</a>
                        <x-primary-button x-bind:disabled="envoi" x-bind:aria-disabled="envoi" class="disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-text="envoi ? {{ Js::from(__('Publication en cours…')) }} : {{ Js::from(__('Publier l\'alerte')) }}">{{ __('Publier l\'alerte') }}</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
