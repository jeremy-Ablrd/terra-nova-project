@props(['service', 'detail' => false])

@php($etat = $service->etatOuverture())

@if ($etat)
    <div {{ $attributes->merge(['class' => 'text-sm text-gray-900']) }}>
        {{-- L'état est écrit en toutes lettres (« Ouvert maintenant… », « Fermé… ») : jamais la couleur seule. --}}
        <p class="font-medium">{{ $etat['texte'] }}</p>

        @if ($detail)
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <caption class="text-left font-medium text-gray-900 pb-1">{{ __('Horaires d\'ouverture : :nom', ['nom' => $service->nom]) }}</caption>
                    <thead class="bg-gray-50 text-left text-gray-700">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">{{ __('Jour') }}</th>
                            <th scope="col" class="px-3 py-2 font-medium">{{ __('Horaires') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($service->lignesHoraires() as $ligne)
                            <tr>
                                <th scope="row" class="px-3 py-2 text-left font-medium {{ $ligne['aujourdhui'] ? 'underline' : '' }}">
                                    {{ $ligne['jour'] }}@if ($ligne['aujourdhui']) <span class="font-semibold">({{ __('ouverture_service.aujourdhui') }})</span>@endif
                                </th>
                                <td class="px-3 py-2">{{ $ligne['horaires'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
