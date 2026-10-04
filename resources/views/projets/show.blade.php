<x-app-layout :title="$projet->titre">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Projets de la ville'), 'url' => route('projets.index')],
            ['label' => $projet->titre],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ $projet->titre }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($errors->has('participation'))
                <p role="alert" class="tn-banner tn-banner--warn max-w-none"><span aria-hidden="true">▲</span> {{ $errors->first('participation') }}</p>
            @endif

            <div class="tn-card p-6 space-y-5">
                <p class="text-gray-800 font-medium">{{ $projet->resume }}</p>

                <section aria-labelledby="consultation">
                    <h2 id="consultation" class="font-medium text-gray-900">{{ __('Consultation') }}</h2>
                    <x-etat-consultation :projet="$projet" detail class="mt-2 block" />
                </section>

                <section aria-labelledby="description">
                    <h2 id="description" class="font-medium text-gray-900">{{ __('Le projet') }}</h2>
                    <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $projet->description }}</p>
                </section>

                @if ($projet->bilan)
                    <section aria-labelledby="bilan">
                        <h2 id="bilan" class="font-medium text-gray-900">{{ __('Ce que la ville retient de la consultation') }}</h2>
                        <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $projet->bilan }}</p>
                    </section>
                @endif
            </div>

            @if ($projet->consultationOuverte())
                <section aria-labelledby="participer" class="tn-card p-6 space-y-3">
                    <h2 id="participer" class="text-xl font-semibold text-gray-900">{{ __('Donner mon avis') }}</h2>
                    <p class="text-gray-700">{{ __('Votre avis n\'est pas un vote : il est lu par la ville, qui vous répond. Vous recevez un numéro de référence et pouvez suivre sa prise en compte.') }}</p>
                    @auth
                        @if (Auth::user()->isCitoyen())
                            <p><x-primary-link href="{{ route('projets.avis.create', $projet) }}">{{ __('Donner mon avis') }}</x-primary-link></p>
                        @endif
                    @else
                        <p><a href="{{ route('login') }}" class="tn-btn tn-btn--primary">{{ __('Se connecter pour donner mon avis') }}</a></p>
                    @endauth
                </section>
            @endif

            <p class="text-sm"><a href="{{ route('projets.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Retour aux projets') }}</a></p>
        </div>
    </div>
</x-app-layout>
