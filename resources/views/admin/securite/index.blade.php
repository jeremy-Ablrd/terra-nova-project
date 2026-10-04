<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Administration'), 'url' => route('admin.index')],
            ['label' => __('Sécurité')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Sécurité') }}</h1>
    </x-slot>

    @php
        $lien = fn (?string $t) => route('admin.securite', array_filter(['type' => $t]));
        $total = $totaux->sum();
    @endphp

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <section class="tn-card p-6 space-y-3" aria-labelledby="dernieres-24h">
                <h2 id="dernieres-24h" class="text-lg font-medium text-gray-900">{{ __('Dernières 24 heures') }}</h2>
                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                    <li class="border border-gray-300 rounded p-3"><span class="text-2xl font-bold">{{ $recents['echec_connexion'] }}</span>
                        <span class="block text-gray-800">{{ trans_choice('{0} Aucun échec de connexion|{1} :count échec de connexion|[2,*] :count échecs de connexion', $recents['echec_connexion']) }}</span></li>
                    <li class="border border-gray-300 rounded p-3"><span class="text-2xl font-bold">{{ $recents['blocage'] }}</span>
                        <span class="block text-gray-800">{{ trans_choice('{0} Aucun blocage|{1} :count blocage|[2,*] :count blocages', $recents['blocage']) }}</span></li>
                    <li class="border border-gray-300 rounded p-3"><span class="text-2xl font-bold">{{ $recents['nouvel_appareil'] }}</span>
                        <span class="block text-gray-800">{{ trans_choice('{0} Aucun nouvel appareil|{1} :count nouvel appareil|[2,*] :count nouveaux appareils', $recents['nouvel_appareil']) }}</span></li>
                    <li class="border border-gray-300 rounded p-3"><span class="text-2xl font-bold">{{ $recents['acces_refuse'] }}</span>
                        <span class="block text-gray-800">{{ trans_choice('{0} Aucun accès refusé|{1} :count accès refusé|[2,*] :count accès refusés', $recents['acces_refuse']) }}</span></li>
                </ul>
            </section>

            <section class="tn-card p-6 space-y-2 text-sm text-gray-800" aria-labelledby="diagnostic">
                <h2 id="diagnostic" class="text-lg font-medium text-gray-900">{{ __('Vérification de la configuration') }}</h2>
                <p><strong>{{ __('Adresse IP détectée pour votre requête : :ip', ['ip' => $ipDetectee]) }}</strong></p>
                <p>
                    @if (filled($proxysConfigures))
                        {{ __('Proxys de confiance (TRUSTED_PROXIES) : renseignés (:valeur).', ['valeur' => $proxysConfigures]) }}
                    @else
                        {{ __('Proxys de confiance (TRUSTED_PROXIES) : vide, aucun proxy n\'est approuvé.') }}
                    @endif
                </p>
                @if ($enTeteRelais && blank($proxysConfigures))
                    <p role="alert" class="rounded border-2 border-red-800 p-2 font-medium text-red-900">{{ __('Votre requête arrive avec un en-tête X-Forwarded-For alors que TRUSTED_PROXIES est vide : l\'adresse IP détectée est probablement celle d\'un proxy. Renseignez TRUSTED_PROXIES, ou désactivez la limite par IP (NOVATERRA_LIMITE_IP=false).') }}</p>
                @endif
                <p>{{ $limiteIp ? __('Limite de connexion par adresse IP : activée.') : __('Limite de connexion par adresse IP : désactivée.') }}</p>
                <p>{{ __('Mode de la politique de sécurité du contenu (CSP) : :mode.', ['mode' => $modeCsp]) }}</p>
                <p>{{ __('Adresse du site (APP_URL), utilisée pour les liens des documents téléchargés : :url', ['url' => $appUrl]) }}</p>
                @if ($appUrlLocale)
                    <p role="alert" class="rounded border-2 border-red-800 p-2 font-medium text-red-900">{{ __('APP_URL pointe vers une adresse locale alors que le site est en production : les liens des documents téléchargés seraient inutilisables. Corrigez APP_URL dans le .env.') }}</p>
                @endif
            </section>

            <nav aria-label="{{ __('Filtrer par type d\'événement') }}" class="px-4 sm:px-0">
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <li>
                        <a href="{{ $lien(null) }}" class="inline-block pb-1 {{ $type === null ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}" @if ($type === null) aria-current="true" @endif>
                            {{ __('Tous') }} <span>({{ $total }})</span>
                        </a>
                    </li>
                    @foreach (\App\Enums\TypeEvenementSecurite::cases() as $option)
                        <li>
                            <a href="{{ $lien($option->value) }}" class="inline-block pb-1 {{ $type === $option ? 'filtre-actif' : 'text-gray-600 hover:text-gray-900' }}" @if ($type === $option) aria-current="true" @endif>
                                {{ $option->label() }} <span>({{ $totaux[$option->value] }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="tn-card">
                @if ($evenements->isEmpty())
                    <p class="p-6 text-sm text-gray-700">{{ $type ? __('Aucun événement de ce type pour le moment.') : __('Aucun événement de sécurité enregistré pour le moment.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Événements de sécurité, du plus récent au plus ancien (30 jours conservés)') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-700">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Date') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Adresse e-mail (masquée)') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Adresse IP') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Page') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('Détail') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($evenements as $evenement)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($evenement->created_at) }}</td>
                                        <th scope="row" class="px-4 py-3 text-left font-medium">{{ $evenement->type->label() }}</th>
                                        <td class="px-4 py-3">{{ $evenement->email_masque ?? '—' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $evenement->ip }}</td>
                                        <td class="px-4 py-3">{{ $evenement->route ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ $evenement->detail ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $evenements->links() }}
        </div>
    </div>
</x-app-layout>
