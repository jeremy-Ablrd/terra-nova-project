<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes données'), 'url' => route('mes-donnees.index')],
            ['label' => __('Récapitulatif de mes demandes')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Récapitulatif de mes demandes') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <p class="px-4 sm:px-0 text-sm text-gray-800">{{ __('Établi le :date', ['date' => \App\Support\DateLocale::format(now())]) }}</p>

            {{-- Impression : le bouton n'apparaît qu'avec JavaScript ; sans lui, la consigne écrite suffit. --}}
            <div class="no-print px-4 sm:px-0 flex flex-wrap items-center gap-4 text-sm text-gray-800">
                <button type="button" hidden x-data x-init="$el.hidden = false" x-on:click="window.print()"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-white hover:bg-gray-700">{{ __('Imprimer') }}</button>
                <span>{{ __('Pour imprimer sans bouton : touches Ctrl + P (⌘ + P sur Mac).') }}</span>
                <a href="{{ route('demandes.export-csv') }}" class="underline">{{ __('Télécharger en CSV') }}</a>
                <a href="{{ route('mes-donnees.index') }}" class="underline">{{ __('Retour à mes données') }}</a>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                @if ($demandes->isEmpty())
                    <p class="p-6 text-sm text-gray-700">{!! __('Vous n\'avez encore aucune demande.') !!}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Toutes mes demandes, de la plus ancienne à la plus récente') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-700">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Référence') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Objet') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Service') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Statut') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Créée le') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Dernière mise à jour') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Traitée le') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($demandes as $demande)
                                    <tr>
                                        <th scope="row" class="px-3 py-2 text-left font-medium whitespace-nowrap">{{ $demande->reference }}</th>
                                        <td class="px-3 py-2">{{ $demande->objet }}</td>
                                        <td class="px-3 py-2">{{ $demande->service?->nom ?? __('À orienter') }}</td>
                                        <td class="px-3 py-2"><x-statut-badge :statut="$demande->statut" /></td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ \App\Support\DateLocale::format($demande->created_at) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ \App\Support\DateLocale::format($demande->updated_at) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $demande->traitee_at ? \App\Support\DateLocale::format($demande->traitee_at) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
