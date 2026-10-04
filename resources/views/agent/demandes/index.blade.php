<x-app-layout :title="__('Centre technique municipal')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Espace agent'), 'url' => route('agent.index')],
            ['label' => __('Centre technique municipal')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Centre technique municipal') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <nav aria-label="{{ __('Filtrer par statut') }}">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li>
                        <a href="{{ route('agent.demandes.index', array_filter(['priorite' => $priorite])) }}"
                           class="inline-block pb-1 {{ $statut === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($statut === null) aria-current="true" @endif>
                            {{ __('Toutes') }} <span>({{ $total }})</span>
                        </a>
                    </li>
                    @foreach (\App\Enums\Statut::cases() as $option)
                        <li>
                            <a href="{{ route('agent.demandes.index', array_filter(['statut' => $option->value, 'priorite' => $priorite])) }}"
                               class="inline-block pb-1 {{ $statut === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                               @if ($statut === $option) aria-current="true" @endif>
                                {{ $option->label() }} <span>({{ $compteurs[$option->value] }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- F80 : filtre par priorité, qui se combine avec le statut. --}}
            <nav aria-label="{{ __('Filtrer par priorité') }}">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li>
                        <a href="{{ route('agent.demandes.index', array_filter(['statut' => $statut?->value])) }}"
                           class="inline-block pb-1 {{ $priorite === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($priorite === null) aria-current="true" @endif>{{ __('Toutes les priorités') }}</a>
                    </li>
                    <li>
                        <a href="{{ route('agent.demandes.index', array_filter(['statut' => $statut?->value, 'priorite' => 'prioritaires'])) }}"
                           class="inline-block pb-1 {{ $priorite === 'prioritaires' ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($priorite === 'prioritaires') aria-current="true" @endif>{{ __('Prioritaires') }} <span>({{ $compteursPriorite['prioritaires'] }})</span></a>
                    </li>
                    <li>
                        <a href="{{ route('agent.demandes.index', array_filter(['statut' => $statut?->value, 'priorite' => 'urgence_medicale'])) }}"
                           class="inline-block pb-1 {{ $priorite === 'urgence_medicale' ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($priorite === 'urgence_medicale') aria-current="true" @endif>{{ __('Urgences médicales') }} <span>({{ $compteursPriorite['urgence_medicale'] }})</span></a>
                    </li>
                </ul>
            </nav>

            <div class="tn-card overflow-hidden p-0">
                @if ($demandes->isEmpty() && $statut)
                    <div class="p-6 text-sm text-gray-600">
                        <p>{{ __('Aucune demande avec ce statut.') }}</p>
                        <p class="mt-2"><a href="{{ route('agent.demandes.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Voir toutes les demandes') }}</a></p>
                    </div>
                @elseif ($demandes->isEmpty() && $priorite)
                    <div class="p-6 text-sm text-gray-600">
                        <p>{{ __('Aucune demande avec cette priorité.') }}</p>
                        <p class="mt-2"><a href="{{ route('agent.demandes.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Voir toutes les demandes') }}</a></p>
                    </div>
                @elseif ($demandes->isEmpty())
                    <p class="p-6 text-sm text-gray-600">{{ __('Aucune demande pour le moment.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">
                                {{ __('Demandes des habitants, de la plus récente à la plus ancienne') }} — {{ __('urgences médicales et demandes prioritaires en tête') }}
                                @if ($statut) — {{ __('statut :') }} {{ $statut->label() }} @endif
                            </caption>
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Référence') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Objet') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Habitant') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Service') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Statut') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Reçue') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($demandes as $demande)
                                    @php
                                        // Mise en avant par une classe (bordure pleine ou pointillée, graisse) ET un texte : jamais la couleur seule.
                                        [$classe, $repere] = match ($demande->statut) {
                                            \App\Enums\Statut::Nouvelle => ['demande-nouvelle', __('À prendre en charge')],
                                            \App\Enums\Statut::EnCours => ['demande-en-cours', __('En cours de traitement')],
                                            default => ['', null],
                                        };
                                    @endphp
                                    <tr class="{{ $classe }}">
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <a href="{{ route('agent.demandes.show', $demande) }}" class="underline font-medium">{{ $demande->reference }}</a>
                                            <x-badge-priorite :priorite="$demande->priorite" class="mt-1" />
                                            @if ($repere)
                                                <span class="block text-xs font-semibold text-gray-700">{{ $repere }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3">{{ $demande->objet }}</td>
                                        <td class="px-6 py-3">{{ $demande->nom_demandeur }}</td>
                                        <td class="px-6 py-3">{{ $demande->service?->nom ?? ($demande->estImportee() ? __('Non précisé') : __('À orienter')) }}</td>
                                        <td class="px-6 py-3"><x-statut-badge :statut="$demande->statut" /></td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <time datetime="{{ $demande->created_at->toIso8601String() }}"
                                                  title="{{ \App\Support\DateLocale::format($demande->created_at) }}">{{ $demande->created_at->diffForHumans() }}</time>
                                        </td>
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
