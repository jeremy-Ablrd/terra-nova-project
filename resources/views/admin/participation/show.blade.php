<x-app-layout :title="__('Contribution :reference', ['reference' => $contribution->reference])">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Administration'), 'url' => route('admin.index')],
            ['label' => __('Participation'), 'url' => route('admin.participation.index')],
            ['label' => $contribution->reference],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Contribution :reference', ['reference' => $contribution->reference]) }}</h1>
    </x-slot>

    @php
        $suivant = $contribution->statut->suivant();
        $champ = 'block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
        $reponseObligatoire = $suivant === \App\Enums\StatutContribution::PriseEnCompte;
    @endphp

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('admin.participation.index') }}" class="text-sm underline text-gray-700 hover:text-gray-900">&larr; {{ __('Retour aux contributions') }}</a>

            @if ($errors->has('transition'))
                <p role="alert" class="tn-banner tn-banner--warn max-w-none"><span aria-hidden="true">▲</span> {{ $errors->first('transition') }}</p>
            @endif

            <div class="tn-card p-6 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h2 class="text-lg font-medium text-gray-900">{{ $contribution->type->label() }} — {{ $contribution->intitule() }}</h2>
                    <x-statut-contribution :statut="$contribution->statut" />
                </div>
                <p class="text-sm text-gray-600">{{ __('Reçue le :date', ['date' => \App\Support\DateLocale::format($contribution->created_at)]) }}
                    @if ($contribution->estAnonymisee()) · {{ __('L\'habitant a supprimé son compte : le texte a été effacé.') }} @endif</p>
                <p class="text-gray-900 whitespace-pre-line">{{ $contribution->message }}</p>
            </div>

            <section aria-labelledby="suivi" class="tn-card p-6 space-y-4">
                <h2 id="suivi" class="text-lg font-semibold text-gray-900">{{ __('Suivi') }}</h2>
                <x-frise-contribution :contribution="$contribution" />

                @if ($contribution->reponse)
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="font-medium text-gray-900">{{ __('Réponse de la ville') }}</h3>
                        <p class="text-sm text-gray-600">{{ __('Le :date', ['date' => \App\Support\DateLocale::format($contribution->reponse_at)]) }}</p>
                        <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $contribution->reponse }}</p>
                    </div>
                @endif
            </section>

            @if ($suivant)
                <section aria-labelledby="traiter" class="tn-card p-6 space-y-4">
                    <h2 id="traiter" class="text-lg font-semibold text-gray-900">{{ $reponseObligatoire ? __('Prendre en compte') : __('Marquer comme examinée') }}</h2>
                    <form method="POST" action="{{ route('admin.participation.statut', $contribution) }}" class="space-y-4" novalidate>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="statut_affiche" value="{{ $contribution->statut->value }}">
                        <div>
                            <x-input-label for="reponse" :value="__('Réponse de la ville').($reponseObligatoire ? ' *' : '')" />
                            <textarea id="reponse" name="reponse" rows="5" maxlength="1000" class="{{ $champ }}" @if ($reponseObligatoire) required aria-required="true" @endif
                                      aria-describedby="reponse_aide{{ $errors->has('reponse') ? ' reponse_erreur' : '' }}"
                                      aria-invalid="{{ $errors->has('reponse') ? 'true' : 'false' }}">{{ old('reponse', $contribution->reponse) }}</textarea>
                            <p id="reponse_aide" class="mt-1 text-xs text-gray-600">
                                {{ $reponseObligatoire ? __('Obligatoire : expliquez à l\'habitant ce que la ville retient. 1 000 caractères au plus.') : __('Facultative à cette étape. 1 000 caractères au plus.') }}
                                {{ __('Ne recopiez pas de donnée personnelle.') }}
                            </p>
                            <x-input-error id="reponse_erreur" :messages="$errors->get('reponse')" class="mt-2" role="alert" />
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>{{ $reponseObligatoire ? __('Prendre en compte') : __('Marquer comme examinée') }}</x-primary-button>
                        </div>
                    </form>
                </section>
            @else
                <p class="text-sm text-gray-700">{{ __('Cette contribution est prise en compte : son statut ne peut plus changer.') }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
