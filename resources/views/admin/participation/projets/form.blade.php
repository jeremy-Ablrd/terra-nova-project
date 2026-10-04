@php
    $existe = $projet->exists;
    $titrePage = $existe ? __('Modifier le projet') : __('Nouveau projet');
    $champ = 'block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
@endphp
<x-app-layout :title="$titrePage">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Administration'), 'url' => route('admin.index')],
            ['label' => __('Participation'), 'url' => route('admin.participation.index')],
            ['label' => __('Projets'), 'url' => route('admin.participation.projets.index')],
            ['label' => $titrePage],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ $titrePage }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="tn-card overflow-hidden p-6">
                <p class="text-sm text-gray-600">{{ __('Un projet publié est visible de tous les visiteurs. Une consultation ouverte permet aux habitants connectés de donner leur avis (ce n\'est pas un vote). Les champs marqués d\'un * sont obligatoires.') }}</p>

                <form method="POST" action="{{ $existe ? route('admin.participation.projets.update', $projet) : route('admin.participation.projets.store') }}" class="mt-6 space-y-6" novalidate>
                    @csrf
                    <x-protection-formulaire />
                    @if ($existe) @method('PUT') @endif

                    <div>
                        <x-input-label for="titre" :value="__('Titre').' *'" />
                        <x-text-input id="titre" name="titre" class="block mt-1 w-full" type="text" :value="old('titre', $projet->titre)" maxlength="150" required aria-required="true"
                                      aria-describedby="titre_aide{{ $errors->has('titre') ? ' titre_erreur' : '' }}" :aria-invalid="$errors->has('titre') ? 'true' : 'false'" />
                        <p id="titre_aide" class="mt-1 text-xs text-gray-600">{{ __('150 caractères maximum.') }}</p>
                        <x-input-error id="titre_erreur" :messages="$errors->get('titre')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="resume" :value="__('Résumé').' *'" />
                        <x-text-input id="resume" name="resume" class="block mt-1 w-full" type="text" :value="old('resume', $projet->resume)" maxlength="255" required aria-required="true"
                                      aria-describedby="resume_aide{{ $errors->has('resume') ? ' resume_erreur' : '' }}" :aria-invalid="$errors->has('resume') ? 'true' : 'false'" />
                        <p id="resume_aide" class="mt-1 text-xs text-gray-600">{{ __('Une phrase, affichée dans la liste des projets. 255 caractères maximum.') }}</p>
                        <x-input-error id="resume_erreur" :messages="$errors->get('resume')" class="mt-2" role="alert" />
                    </div>

                    <div>
                        <x-input-label for="description" :value="__('Description').' *'" />
                        <textarea id="description" name="description" rows="8" maxlength="5000" required aria-required="true" class="{{ $champ }}"
                                  aria-describedby="description_aide{{ $errors->has('description') ? ' description_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}">{{ old('description', $projet->description) }}</textarea>
                        <p id="description_aide" class="mt-1 text-xs text-gray-600">{{ __('5 000 caractères maximum.') }}</p>
                        <x-input-error id="description_erreur" :messages="$errors->get('description')" class="mt-2" role="alert" />
                    </div>

                    <fieldset class="grid gap-6 sm:grid-cols-2">
                        <legend class="font-medium text-gray-900">{{ __('Consultation (facultative)') }}</legend>
                        <div>
                            <x-input-label for="consultation_debut_at" :value="__('Début de la consultation')" />
                            <x-text-input id="consultation_debut_at" name="consultation_debut_at" class="block mt-1 w-full" type="datetime-local" :value="old('consultation_debut_at', $debut)"
                                          aria-describedby="debut_aide{{ $errors->has('consultation_debut_at') ? ' debut_erreur' : '' }}" />
                            <p id="debut_aide" class="mt-1 text-xs text-gray-600">{{ __('Heure de La Réunion. Sans début ni fin, le projet est présenté sans consultation.') }}</p>
                            <x-input-error id="debut_erreur" :messages="$errors->get('consultation_debut_at')" class="mt-2" role="alert" />
                        </div>
                        <div>
                            <x-input-label for="consultation_fin_at" :value="__('Fin de la consultation')" />
                            <x-text-input id="consultation_fin_at" name="consultation_fin_at" class="block mt-1 w-full" type="datetime-local" :value="old('consultation_fin_at', $fin)"
                                          aria-describedby="fin_aide{{ $errors->has('consultation_fin_at') ? ' fin_erreur' : '' }}" />
                            <p id="fin_aide" class="mt-1 text-xs text-gray-600">{{ __('Doit être après le début. Après cette date, les avis ne sont plus acceptés.') }}</p>
                            <x-input-error id="fin_erreur" :messages="$errors->get('consultation_fin_at')" class="mt-2" role="alert" />
                        </div>
                    </fieldset>

                    <div>
                        <x-input-label for="bilan" :value="__('Ce que la ville retient de la consultation')" />
                        <textarea id="bilan" name="bilan" rows="5" maxlength="2000" class="{{ $champ }}"
                                  aria-describedby="bilan_aide{{ $errors->has('bilan') ? ' bilan_erreur' : '' }}"
                                  aria-invalid="{{ $errors->has('bilan') ? 'true' : 'false' }}">{{ old('bilan', $projet->bilan) }}</textarea>
                        <p id="bilan_aide" class="mt-1 text-xs text-gray-600">{{ __('Facultatif, à rédiger après la consultation. 2 000 caractères maximum. Ne recopiez aucune donnée personnelle.') }}</p>
                        <x-input-error id="bilan_erreur" :messages="$errors->get('bilan')" class="mt-2" role="alert" />
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="publie" value="0">
                        <input id="publie" name="publie" type="checkbox" value="1" class="h-6 w-6 rounded border-2 border-gray-400" @checked(old('publie', $projet->estPublie() ? '1' : '0') == '1')>
                        <x-input-label for="publie" :value="__('Publié : visible de tous les visiteurs')" class="!mt-0" />
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <a href="{{ route('admin.participation.projets.index') }}" class="text-sm underline text-gray-700 hover:text-gray-900">{{ __('Annuler') }}</a>
                        <x-primary-button>{{ $existe ? __('Enregistrer') : __('Créer le projet') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
