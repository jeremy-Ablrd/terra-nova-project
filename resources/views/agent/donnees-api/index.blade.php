<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Espace agent'), 'url' => route('agent.index')],
            ['label' => __('Données API')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Données API') }}</h1>
    </x-slot>

    @php
        // Les filtres et le tri se combinent : chaque lien garde les autres paramètres et repart de la page 1.
        $lien = fn (array $change) => route('agent.donnees-api.index', array_filter(
            array_merge(['type' => $type, 'vague' => $vague, 'tri' => $tri === 'sort_order' && $sens === 'asc' ? null : $tri, 'sens' => $sens === 'asc' ? null : $sens], $change),
            fn ($v) => $v !== null
        ));
        $colonnes = [
            'sort_order' => __('Ordre'),
            'difficulty_level' => __('Difficulté'),
            'xp_total' => __('XP total'),
            'visible_since_wave' => __('Vague d\'apparition'),
            'first_seen_at' => __('Premier vu'),
        ];
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('succes'))
                <p role="status" class="rounded border-2 border-gray-800 bg-white p-3 text-sm font-medium text-gray-900">{{ session('succes') }}</p>
            @endif

            <div class="px-4 sm:px-0 space-y-2 text-sm text-gray-800">
                <p>{{ __('Demandes transmises par l\'API Terra Nova, en lecture seule. L\'actualisation se fait côté administrateur.') }}</p>
                @if ($erreur)
                    <p role="alert" class="rounded border-2 border-red-800 bg-white p-3 font-medium text-red-900">
                        {{ __('La dernière synchronisation avec l\'API a échoué : :message', ['message' => $erreur]) }}
                        {{ __('Les dernières données valides restent affichées.') }}
                    </p>
                @endif
                <p>
                    @if ($lastSync)
                        {{ __('Dernière synchro réussie : :date', ['date' => \App\Support\DateLocale::format($lastSync)]) }}
                    @else
                        {{ __('Aucune synchronisation réussie pour l\'instant.') }}
                    @endif
                </p>
                <form method="POST" action="{{ route('agent.donnees-api.vues') }}">
                    @csrf
                    <x-secondary-button type="submit">{{ __('Marquer comme vues') }}</x-secondary-button>
                </form>
            </div>

            <nav aria-label="{{ __('Filtrer par type de demandeur') }}" class="px-4 sm:px-0">
                <p class="text-sm font-medium text-gray-900">{{ __('Type de demandeur') }}</p>
                <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <li>
                        <a href="{{ $lien(['type' => null, 'page' => null]) }}"
                           class="inline-block pb-1 {{ $type === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($type === null) aria-current="true" @endif>{{ __('Tous') }} <span>({{ $total }})</span></a>
                    </li>
                    @foreach ($parType as $nom => $nombre)
                        <li>
                            <a href="{{ $lien(['type' => $nom]) }}"
                               class="inline-block pb-1 {{ $type === $nom ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                               @if ($type === $nom) aria-current="true" @endif>{{ $nom }} <span>({{ $nombre }})</span></a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="{{ __('Filtrer par vague') }}" class="px-4 sm:px-0">
                <p class="text-sm font-medium text-gray-900">{{ __('Vague d\'apparition') }}</p>
                <ul class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <li>
                        <a href="{{ $lien(['vague' => null]) }}"
                           class="inline-block pb-1 {{ $vague === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                           @if ($vague === null) aria-current="true" @endif>{{ __('Toutes') }} <span>({{ $total }})</span></a>
                    </li>
                    @foreach ($parVague as $numero => $nombre)
                        <li>
                            <a href="{{ $lien(['vague' => $numero]) }}"
                               class="inline-block pb-1 {{ $vague === (int) $numero ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}"
                               @if ($vague === (int) $numero) aria-current="true" @endif>{{ __('Vague :n', ['n' => $numero]) }} <span>({{ $nombre }})</span></a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="tn-card overflow-hidden p-0">
                @if ($lignes->isEmpty())
                    <div class="p-6 text-sm text-gray-600">
                        <p>{{ $total === 0 ? __('Aucune donnée reçue de l\'API pour le moment.') : __('Aucune donnée ne correspond à ces filtres.') }}</p>
                        @if ($total > 0)
                            <p class="mt-2"><a href="{{ route('agent.donnees-api.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Voir toutes les données') }}</a></p>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Demandes transmises par l\'API, triées par :colonne (:sens)', ['colonne' => $colonnes[$tri], 'sens' => $sens === 'asc' ? __('croissant') : __('décroissant')]) }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Demandeur') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Message public') }}</th>
                                    @foreach (['difficulty_level', 'xp_total', 'visible_since_wave', 'first_seen_at'] as $col)
                                        @php($actif = $tri === $col)
                                        <th scope="col" class="px-4 py-3 font-medium" @if ($actif) aria-sort="{{ $sens === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                            <a href="{{ $lien(['tri' => $col, 'sens' => $actif && $sens === 'asc' ? 'desc' : 'asc', 'page' => null]) }}"
                                               class="underline {{ $actif ? 'font-bold text-gray-900' : '' }}">{{ $colonnes[$col] }}</a>
                                            @if ($actif) <span>({{ $sens === 'asc' ? __('croissant') : __('décroissant') }})</span> @endif
                                        </th>
                                    @endforeach
                                    <th scope="col" class="px-4 py-3 font-medium">
                                        <a href="{{ $lien(['tri' => 'sort_order', 'sens' => $tri === 'sort_order' && $sens === 'asc' ? 'desc' : 'asc', 'page' => null]) }}"
                                           class="underline {{ $tri === 'sort_order' ? 'font-bold text-gray-900' : '' }}">{{ $colonnes['sort_order'] }}</a>
                                        @if ($tri === 'sort_order') <span>({{ $sens === 'asc' ? __('croissant') : __('décroissant') }})</span> @endif
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($lignes as $ligne)
                                    <tr>
                                        <th scope="row" class="px-4 py-3 text-left font-medium whitespace-nowrap">
                                            {{ $ligne->request_code }}
                                            @if ($ligne->first_seen_at && ($vuesAt === null || $ligne->first_seen_at->gt($vuesAt)))
                                                <span class="block text-xs font-semibold border border-gray-700 rounded px-1 w-fit">{{ __('Nouvelle') }}</span>
                                            @endif
                                        </th>
                                        <td class="px-4 py-3">{{ $ligne->requester_name }} <span class="block text-xs text-gray-600">({{ $ligne->requester_type }})</span></td>
                                        <td class="px-4 py-3 max-w-xl">{{ $ligne->message_public }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $ligne->difficulty }} ({{ $ligne->difficulty_level }})</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $ligne->xp_total }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $ligne->visible_since_wave }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($ligne->first_seen_at)<time datetime="{{ $ligne->first_seen_at->toIso8601String() }}">{{ \App\Support\DateLocale::format($ligne->first_seen_at) }}</time>@endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $ligne->sort_order }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $lignes->links() }}
        </div>
    </div>
</x-app-layout>
