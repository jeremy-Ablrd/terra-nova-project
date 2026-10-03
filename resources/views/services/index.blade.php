<x-app-layout :title="__('Services municipaux')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Services municipaux')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Services municipaux') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-700">{{ __('Retrouvez les principaux services de Nova Terra, leurs horaires et leur disponibilité avant de commencer une démarche.') }}</p>

            {{-- Synthèse : ce qui est disponible, ce qui ne l'est pas. --}}
            <p class="text-base font-medium text-gray-900">{{ $synthese }}</p>

            <nav aria-label="{{ __('Filtrer par état') }}">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li>
                        <a href="{{ route('services.index', array_filter(['categorie' => $categorie?->value])) }}"
                           class="inline-block pb-1 {{ $etat === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($etat === null) aria-current="true" @endif>
                            {{ __('Tous les états') }} <span>({{ $totalEtat }})</span>
                        </a>
                    </li>
                    @foreach (\App\Enums\Disponibilite::cases() as $option)
                        <li>
                            <a href="{{ route('services.index', array_filter(['categorie' => $categorie?->value, 'etat' => $option->value])) }}"
                               class="inline-block pb-1 {{ $etat === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                               @if ($etat === $option) aria-current="true" @endif>
                                {{ $option->label() }} <span>({{ $compteursEtat[$option->value] }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="{{ __('Filtrer par catégorie') }}">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li>
                        <a href="{{ route('services.index', array_filter(['etat' => $etat?->value])) }}"
                           class="inline-block pb-1 {{ $categorie === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($categorie === null) aria-current="true" @endif>
                            {{ __('Tous') }} <span>({{ $total }})</span>
                        </a>
                    </li>
                    @foreach (\App\Enums\CategorieService::cases() as $option)
                        <li>
                            <a href="{{ route('services.index', array_filter(['categorie' => $option->value, 'etat' => $etat?->value])) }}"
                               class="inline-block pb-1 {{ $categorie === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                               @if ($categorie === $option) aria-current="true" @endif>
                                {{ $option->label() }} <span>({{ $compteurs[$option->value] }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @if ($services->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">
                    <p>{{ $etat ? __('Aucun service ne correspond à ces filtres.') : __('Aucun service dans cette catégorie.') }}</p>
                    <p class="mt-2"><a href="{{ route('services.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Voir tous les services') }}</a></p>
                </div>
            @else
                <ul class="grid gap-6 md:grid-cols-2">
                    @foreach ($services as $service)
                        <li class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-col gap-3">
                            <article aria-labelledby="service-{{ $service->id }}" class="flex flex-col gap-3">
                                <div>
                                    <h2 id="service-{{ $service->id }}" class="text-lg font-semibold text-gray-900">{{ $service->nom }}</h2>
                                    <p class="mt-1 text-xs text-gray-600">
                                        {{ $service->categorie->label() }}
                                        @if ($service->prioritaire) · <span class="font-semibold text-gray-900">{{ __('Service prioritaire') }}</span> @endif
                                    </p>
                                </div>
                                <p class="text-sm text-gray-800">{{ $service->resume }}</p>
                                @if ($service->adresse || $service->quartier)
                                    <p class="text-sm text-gray-700">{{ collect([$service->adresse, $service->quartier])->filter()->implode(' — ') }}</p>
                                @endif
                                <x-disponibilite-service :service="$service" />
                                <x-prochaine-action :service="$service" court />
                                <p class="text-sm">
                                    <a href="{{ route('services.show', $service) }}" class="underline font-medium text-gray-900">{{ __('Voir la fiche') }}<span class="sr-only"> {{ __('du service') }} {{ $service->nom }}</span></a>
                                </p>
                            </article>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
