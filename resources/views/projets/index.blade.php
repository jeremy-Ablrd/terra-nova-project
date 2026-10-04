<x-app-layout :title="__('Projets de la ville')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Projets de la ville')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Projets de la ville') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <section aria-labelledby="comment" class="tn-card p-6 space-y-3">
                <h2 id="comment" class="text-xl font-semibold text-gray-900">{{ __('Comment participer') }}</h2>
                <ul class="list-disc ps-5 space-y-1 text-gray-800">
                    <li>{{ __('Lisez les projets en cours de la ville et donnez votre avis quand une consultation est ouverte.') }}</li>
                    <li>{{ __('Un avis n\'est pas un vote : il n\'est pas compté, il est lu, et la ville vous répond.') }}</li>
                    <li>{{ __('Chaque contribution reçoit un numéro de référence (PA-…) et un suivi : reçue, examinée, prise en compte.') }}</li>
                    <li>{{ __('Vous pouvez aussi proposer une idée pour améliorer la colonie.') }}</li>
                </ul>
                <p class="flex flex-wrap items-center gap-4">
                    @auth
                        @if (Auth::user()->isCitoyen())
                            <x-primary-link href="{{ route('idees.create') }}">{{ __('Proposer une idée') }}</x-primary-link>
                            <a href="{{ route('mes-contributions.index') }}" class="underline text-gray-800 hover:text-gray-900">{{ __('Mes contributions') }}</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="tn-btn tn-btn--primary">{{ __('Se connecter pour participer') }}</a>
                        <a href="{{ route('register') }}" class="underline text-gray-800 hover:text-gray-900">{{ __('Créer mon compte habitant') }}</a>
                    @endauth
                </p>
            </section>

            @if ($projets->isEmpty())
                <p class="tn-card p-6 text-gray-700">{{ __('Aucun projet n\'est présenté pour le moment.') }}</p>
            @else
                <ul class="grid gap-6 md:grid-cols-2">
                    @foreach ($projets as $projet)
                        <li class="tn-card p-6 flex flex-col gap-3">
                            <h2 class="text-xl font-semibold text-gray-900"><a href="{{ route('projets.show', $projet) }}" class="hover:underline">{{ $projet->titre }}</a></h2>
                            <x-etat-consultation :projet="$projet" />
                            <p class="text-gray-700">{{ $projet->resume }}</p>
                            <p class="mt-auto"><a href="{{ route('projets.show', $projet) }}" class="tn-btn tn-btn--secondary">{{ __('Lire le projet') }}<span class="sr-only"> : {{ $projet->titre }}</span></a></p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
