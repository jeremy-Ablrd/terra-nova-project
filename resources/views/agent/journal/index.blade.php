<x-app-layout :title="__('Journal d\'activité')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Espace agent'), 'url' => route('agent.index')],
            ['label' => __('Journal d\'activité')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Journal d\'activité') }}</h1>
    </x-slot>

    @php
        // Chaque filtre conserve l'autre dans ses liens.
        $lien = fn (?string $a, ?string $r) => route('agent.journal.index', array_filter(['action' => $a, 'role' => $r], fn ($valeur) => $valeur !== null));
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-700">{{ __('Qui a fait quoi, quand et sur quel objet, pour les agents et les administrateurs. Le journal est en lecture seule : aucune entrée ne peut être modifiée ni supprimée.') }}</p>

            <nav aria-label="{{ __('Filtrer par type d\'action') }}">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li>
                        <a href="{{ $lien(null, $role?->value) }}"
                           class="inline-block pb-1 {{ $action === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($action === null) aria-current="true" @endif>
                            {{ __('Toutes les actions') }} <span>({{ $totalAction }})</span>
                        </a>
                    </li>
                    @foreach (\App\Enums\ActionJournal::cases() as $option)
                        @if ($compteursAction[$option->value] > 0 || $action === $option)
                            <li>
                                <a href="{{ $lien($option->value, $role?->value) }}"
                                   class="inline-block pb-1 {{ $action === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                                   @if ($action === $option) aria-current="true" @endif>
                                    {{ $option->label() }} <span>({{ $compteursAction[$option->value] }})</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="{{ __('Filtrer par rôle de l\'acteur') }}">
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <li>
                        <a href="{{ $lien($action?->value, null) }}"
                           class="inline-block pb-1 {{ $role === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($role === null) aria-current="true" @endif>
                            {{ __('Tous les rôles') }} <span>({{ $totalRole }})</span>
                        </a>
                    </li>
                    @foreach ($roles as $option)
                        @if ($compteursRole[$option->value] > 0 || $role === $option)
                            <li>
                                <a href="{{ $lien($action?->value, $option->value) }}"
                                   class="inline-block pb-1 {{ $role === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                                   @if ($role === $option) aria-current="true" @endif>
                                    {{ $option->label() }} <span>({{ $compteursRole[$option->value] }})</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl">
                @if ($entrees->isEmpty())
                    @if ($action || $role)
                        <div class="p-6 text-sm text-gray-600">
                            <p>{{ __('Aucune activité ne correspond à ces filtres.') }}</p>
                            <p class="mt-2"><a href="{{ route('agent.journal.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Voir tout le journal') }}</a></p>
                        </div>
                    @else
                        <p class="p-6 text-sm text-gray-600">{{ __('Aucune activité enregistrée pour le moment.') }}</p>
                    @endif
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">
                                {{ __('Journal d\'activité, de l\'action la plus récente à la plus ancienne') }}
                                @if ($action) — {{ __('action :') }} {{ $action->label() }} @endif
                                @if ($role) — {{ __('rôle :') }} {{ $role->label() }} @endif
                            </caption>
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Acteur') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Objet') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Détail') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($entrees as $entree)
                                    <tr>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($entree->created_at) }}</td>
                                        <td class="px-6 py-3">
                                            {{ $entree->acteur_nom }}
                                            <span class="block text-xs text-gray-600">{{ $entree->acteur_role->label() }}</span>
                                        </td>
                                        <td class="px-6 py-3 font-medium">{{ $entree->action->label() }}</td>
                                        <td class="px-6 py-3">
                                            <span class="block text-xs text-gray-600">{{ $entree->typeObjetLibelle() }}</span>
                                            {{ $entree->objet_libelle }}
                                        </td>
                                        <td class="px-6 py-3">{{ $entree->detail ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $entrees->links() }}
        </div>
    </div>
</x-app-layout>
