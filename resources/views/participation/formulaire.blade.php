<x-app-layout :title="$titrePage">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="array_merge([['label' => __('Accueil'), 'url' => url('/')]], $fil)" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ $titrePage }}</h1>
    </x-slot>

    @php
        $champ = 'block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
        $estIdee = $type === \App\Enums\TypeContribution::Idee;
        $max = $estIdee ? 1500 : 1000;
    @endphp

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($sousTitre)
                <p class="text-lg font-medium text-gray-900">{{ $sousTitre }}</p>
            @endif

            @if ($deja)
                <div role="status" class="tn-banner tn-banner--info max-w-none flex-wrap items-center">
                    <span aria-hidden="true">◆</span>
                    <p class="text-sm font-medium">
                        {{ $type === \App\Enums\TypeContribution::Avis
                            ? __('Vous avez déjà donné votre avis sur ce projet (référence :reference). Il est figé : la ville le lit et vous répond.', ['reference' => $deja->reference])
                            : __('Vous avez déjà laissé un commentaire sur ce service (référence :reference). Il est figé : la ville le lit.', ['reference' => $deja->reference]) }}
                        <a href="{{ route('mes-contributions.show', $deja) }}" class="underline">{{ __('Suivre ma contribution :reference', ['reference' => $deja->reference]) }}</a>
                    </p>
                </div>
            @else
                <div class="tn-card p-6 space-y-5">
                    <div class="text-sm text-gray-700 space-y-1">
                        @if ($type === \App\Enums\TypeContribution::Avis)
                            <p>{{ __('Votre avis n\'est pas un vote : il n\'est pas compté, il est lu par la ville, qui vous répond.') }}</p>
                        @elseif ($estIdee)
                            <p>{{ __('Proposez une idée pour améliorer la colonie. La ville la lit et vous répond.') }}</p>
                        @else
                            <p>{{ __('Racontez votre expérience de ce service. La ville lit votre commentaire.') }}</p>
                        @endif
                        <p>{{ __('Votre contribution n\'est pas affichée publiquement. Une fois envoyée, elle ne peut plus être modifiée : relisez-la avant de l\'envoyer.') }}</p>
                        <p>{{ __('Vous recevrez un numéro de référence et pourrez suivre sa prise en compte dans « Mes contributions ».') }}</p>
                        <p>{{ __('Les champs marqués d\'un * sont obligatoires.') }}</p>
                    </div>

                    <form method="POST" action="{{ $action }}" class="space-y-6" novalidate>
                        @csrf
                        <x-protection-formulaire />
                        @if ($estIdee)
                            <div>
                                <x-input-label for="titre" :value="__('Titre de l\'idée').' *'" />
                                <x-text-input id="titre" name="titre" class="block mt-1 w-full" type="text" :value="old('titre')" maxlength="120" required aria-required="true"
                                              aria-describedby="titre_aide{{ $errors->has('titre') ? ' titre_erreur' : '' }}" :aria-invalid="$errors->has('titre') ? 'true' : 'false'" />
                                <p id="titre_aide" class="mt-1 text-xs text-gray-600">{{ __('Entre 5 et 120 caractères.') }}</p>
                                <x-input-error id="titre_erreur" :messages="$errors->get('titre')" class="mt-2" role="alert" />
                            </div>
                        @endif

                        <div>
                            <x-input-label for="message" :value="($estIdee ? __('Décrivez votre idée') : ($type === \App\Enums\TypeContribution::Avis ? __('Votre avis') : __('Votre commentaire'))).' *'" />
                            <textarea id="message" name="message" rows="6" maxlength="{{ $max }}" required aria-required="true" class="{{ $champ }}"
                                      aria-describedby="message_aide{{ $errors->has('message') ? ' message_erreur' : '' }}"
                                      aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}">{{ old('message') }}</textarea>
                            <p id="message_aide" class="mt-1 text-xs text-gray-600">{{ $estIdee ? __('Entre 20 et 1 500 caractères.') : __('Entre 10 et 1 000 caractères.') }}</p>
                            <x-input-error id="message_erreur" :messages="$errors->get('message')" class="mt-2" role="alert" />
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-4">
                            <a href="{{ $retour['url'] }}" class="text-sm underline text-gray-700 hover:text-gray-900">{{ __('Annuler') }}</a>
                            <x-primary-button>{{ __('Envoyer ma contribution') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            <p class="text-sm"><a href="{{ $retour['url'] }}" class="underline text-gray-700 hover:text-gray-900">{{ $retour['label'] }}</a></p>
        </div>
    </div>
</x-app-layout>
