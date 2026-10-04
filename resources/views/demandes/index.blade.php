<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes demandes')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">Mes demandes</h1>
            @if (Auth::user()->isCitoyen())
                <x-primary-link href="{{ route('contact.create') }}">{{ __('Nouvelle demande') }}</x-primary-link>
            @endif
        </div>
    </x-slot>

    @php
        // Filtre et recherche se combinent : chaque lien garde l'autre paramètre et repart de la page 1.
        $lien = fn (?string $s, ?string $q) => route('demandes.index', array_filter(['statut' => $s, 'q' => $q], fn ($v) => $v !== null && $v !== ''));
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <x-notifications-demandes />

            {{-- Recherche (GET, sans JavaScript) : référence ou mot de l'objet. --}}
            <form method="GET" action="{{ route('demandes.index') }}" role="search" aria-label="{{ __('Rechercher dans mes demandes') }}" class="px-4 sm:px-0 flex flex-wrap items-end gap-3">
                @if ($statut)
                    <input type="hidden" name="statut" value="{{ $statut->value }}">
                @endif
                <div>
                    <x-input-label for="recherche" :value="__('Référence ou mot de l\'objet')" />
                    <x-text-input id="recherche" name="q" type="search" maxlength="100" class="mt-1 block w-full sm:w-80" :value="$recherche" />
                </div>
                <x-primary-button type="submit">{{ __('Rechercher') }}</x-primary-button>
                @if ($recherche !== '')
                    <a href="{{ $lien($statut?->value, null) }}" class="underline text-sm text-gray-700 hover:text-gray-900">{{ __('Effacer la recherche') }}</a>
                @endif
            </form>

            @if ($recherche !== '')
                <p class="px-4 sm:px-0 text-sm text-gray-800">{{ __('Recherche : « :texte »', ['texte' => $recherche]) }}</p>
            @endif

            {{-- Filtre par statut : liens GET avec compteurs ; l'actif est en gras souligné, avec aria-current. --}}
            <nav aria-label="{{ __('Filtrer par statut') }}" class="px-4 sm:px-0">
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <li>
                        <a href="{{ $lien(null, $recherche) }}"
                           class="inline-block pb-1 {{ $statut === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($statut === null) aria-current="true" @endif>
                            {{ __('Toutes') }} <span>({{ $total }})</span>
                        </a>
                    </li>
                    @foreach (\App\Enums\Statut::cases() as $option)
                        <li>
                            <a href="{{ $lien($option->value, $recherche) }}"
                               class="inline-block pb-1 {{ $statut === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                               @if ($statut === $option) aria-current="true" @endif>
                                {{ $option->label() }} <span>({{ $compteurs[$option->value] }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl">
                @if ($demandes->isEmpty())
                    <div class="p-6 text-sm text-gray-600">
                        @if ($recherche !== '')
                            <p>{{ __('Aucun résultat pour cette recherche.') }}</p>
                            <p class="mt-2"><a href="{{ $lien(null, null) }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Effacer la recherche') }}</a></p>
                        @elseif ($statut)
                            <p>{{ __('Aucune demande avec ce statut.') }}</p>
                            <p class="mt-2"><a href="{{ $lien(null, null) }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Voir toutes mes demandes') }}</a></p>
                        @else
                            <p>{!! __('Vous n\'avez encore aucune demande.') !!}</p>
                            <p class="mt-2"><a href="{{ route('contact.create') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Contacter la mairie') }}</a></p>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">
                                {{ __('Mes demandes, de la plus récente à la plus ancienne') }}
                                @if ($statut) — {{ __('statut :') }} {{ $statut->label() }} @endif
                                @if ($recherche !== '') — {{ __('recherche :') }} {{ $recherche }} @endif
                            </caption>
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Référence') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Objet') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Service') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Statut') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Créée le') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Dernière mise à jour') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($demandes as $demande)
                                    <tr>
                                        <th scope="row" class="px-6 py-3 text-left font-normal whitespace-nowrap">
                                            <a href="{{ route('demandes.show', $demande) }}" class="underline text-gray-900 hover:text-gray-600">{{ $demande->reference }}</a>
                                        </th>
                                        <td class="px-6 py-3">{{ $demande->objet }}</td>
                                        <td class="px-6 py-3">{{ $demande->service?->nom ?? __('À orienter') }}</td>
                                        <td class="px-6 py-3"><x-statut-badge :statut="$demande->statut" /></td>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($demande->created_at) }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($demande->updated_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $demandes->links() }}
        </div>
    </div>
</x-app-layout>
