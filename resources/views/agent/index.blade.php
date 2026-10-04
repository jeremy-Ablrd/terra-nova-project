<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">Espace agent</h1>
    </x-slot>

    @php
        $carte = 'block tn-card p-4 border border-gray-200 hover:border-gray-500';
        $lienStatut = fn (string $statut) => route('agent.demandes.index', ['statut' => $statut]);
    @endphp

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-alertes-connexion />

            <p class="px-4 sm:px-0 text-lg font-medium text-gray-900">Bienvenue {{ Auth::user()->name }}</p>

            <section aria-labelledby="charge-de-travail">
                <h2 id="charge-de-travail" class="text-lg font-semibold text-gray-900 mb-2 px-4 sm:px-0">{{ __('Charge de travail') }}</h2>
                <p class="mb-3 px-4 sm:px-0 text-3xl font-bold text-gray-900">
                    <x-compteur-en-attente :nombre="$nouvelles" visible />
                </p>
                <ul class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <li><a href="{{ $lienStatut('nouvelle') }}" class="{{ $carte }}">
                        <span class="text-2xl font-bold">{{ $nouvelles }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucune nouvelle demande|{1} :count nouvelle demande|[2,*] :count nouvelles demandes', $nouvelles) }}</span>
                    </a></li>
                    <li><a href="{{ $lienStatut('en_cours') }}" class="{{ $carte }}">
                        <span class="text-2xl font-bold">{{ $enCours }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucune demande en cours|{1} :count demande en cours|[2,*] :count demandes en cours', $enCours) }}</span>
                    </a></li>
                    <li><a href="{{ $lienStatut('traitee') }}" class="{{ $carte }}">
                        <span class="text-2xl font-bold">{{ $traitees }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucune demande traitée|{1} :count demande traitée|[2,*] :count demandes traitées', $traitees) }}</span>
                    </a></li>
                </ul>
                <ul class="mt-3 px-4 sm:px-0 text-sm text-gray-800 space-y-1">
                    <li>{{ trans_choice('{0} Aucune demande créée ces 7 derniers jours|{1} :count demande créée ces 7 derniers jours|[2,*] :count demandes créées ces 7 derniers jours', $recentes) }}</li>
                    <li>
                        @if ($ancienneteJours === null)
                            {{ __('Aucune demande nouvelle en attente.') }}
                        @else
                            {{ __('Plus ancienne demande encore nouvelle :') }}
                            {{ trans_choice('{0} moins d\'un jour|{1} :count jour|[2,*] :count jours', $ancienneteJours) }}
                        @endif
                    </li>
                </ul>
                <p class="mt-3 px-4 sm:px-0 flex flex-wrap items-center gap-4 text-sm">
                    <a href="{{ route('agent.demandes.index', ['statut' => \App\Enums\Statut::Nouvelle->value]) }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Centre technique municipal : demandes en attente') }}</a>
                    <a href="{{ route('agent.demandes.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Centre technique municipal : toutes les demandes') }}</a>
                </p>
            </section>

            <section aria-labelledby="plateforme">
                <h2 id="plateforme" class="text-lg font-semibold text-gray-900 mb-2 px-4 sm:px-0">{{ __('Plateforme') }}</h2>
                <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <li><a href="{{ route('alertes.index') }}" class="{{ $carte }}">
                        <span class="text-2xl font-bold">{{ $alertesActives }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucune alerte active|{1} :count alerte active|[2,*] :count alertes actives', $alertesActives) }}</span>
                    </a></li>
                    {{-- Pas de lien : un test existant interdit le lien vers le catalogue public sur l'accueil agent (à valider avec le développeur). --}}
                    <li><div class="{{ $carte }} hover:border-gray-200">
                        <span class="text-2xl font-bold">{{ $servicesInterrompus }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucun service interrompu|{1} :count service interrompu|[2,*] :count services interrompus', $servicesInterrompus) }}</span>
                    </div></li>
                    <li><div class="{{ $carte }} hover:border-gray-200">
                        <span class="text-2xl font-bold">{{ $servicesDesactives }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucun service désactivé|{1} :count service désactivé|[2,*] :count services désactivés', $servicesDesactives) }}</span>
                    </div></li>
                    <li><div class="{{ $carte }} hover:border-gray-200">
                        <span class="text-2xl font-bold">{{ $comptesCitoyens }}</span>
                        <span class="block text-sm text-gray-800">{{ trans_choice('{0} Aucun compte citoyen|{1} :count compte citoyen|[2,*] :count comptes citoyens', $comptesCitoyens) }}</span>
                    </div></li>
                </ul>
            </section>

            <section class="tn-card overflow-hidden p-6" aria-labelledby="suivi-activite">
                <h2 id="suivi-activite" class="text-lg font-semibold text-gray-900">{{ __('Suivi de l\'activité') }}</h2>
                @if ($entrees->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">{{ __('Aucune activité enregistrée pour le moment.') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-gray-100 text-sm text-gray-800">
                        @foreach ($entrees as $entree)
                            <li class="py-2">
                                <time datetime="{{ $entree->created_at->toIso8601String() }}" class="text-gray-600">{{ \App\Support\DateLocale::format($entree->created_at) }}</time>
                                — {{ $entree->acteur_nom }} : {{ $entree->action->label() }} ({{ $entree->objet_libelle }})
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-4 text-sm">
                    <a href="{{ route('agent.journal.index') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Voir tout le journal') }} — {{ __('Journal d\'activité') }}</a>
                </p>
            </section>

            <section class="tn-card overflow-hidden p-6" aria-labelledby="synchro-api">
                <h2 id="synchro-api" class="text-lg font-semibold text-gray-900">{{ __('État de la synchronisation API') }}</h2>
                @if ($sync['erreur'])
                    <p role="alert" class="mt-2 rounded border-2 border-red-800 p-2 text-sm font-medium text-red-900">{{ __('Dernière erreur : :message', ['message' => $sync['erreur']]) }}</p>
                @endif
                @if ($sync['at'])
                    <p class="mt-2 text-sm text-gray-800">{{ __('Dernière synchro réussie : :date', ['date' => \App\Support\DateLocale::format($sync['at'])]) }}</p>
                    @if ($sync['session'])
                        <p class="mt-2 text-sm text-gray-600">{{ __('Valeurs au moment de la dernière synchro à :heure :', ['heure' => \App\Support\DateLocale::heure($sync['at'])]) }}</p>
                        <ul class="mt-1 text-sm text-gray-800 space-y-1">
                            <li>{{ __('Vague actuelle : :n', ['n' => $sync['session']['current_wave'] ?? __('inconnue')]) }}</li>
                            <li>{{ trans_choice('{0} Aucune demande visible|{1} :count demande visible|[2,*] :count demandes visibles', (int) ($sync['session']['visible_requests_count'] ?? 0)) }}</li>
                            <li>
                                @if (isset($sync['session']['next_wave_number']))
                                    {{ __('Vague suivante : :n', ['n' => $sync['session']['next_wave_number']]) }}
                                @else
                                    {{ __('Aucune vague suivante annoncée.') }}
                                @endif
                            </li>
                        </ul>
                    @endif
                @else
                    <p class="mt-2 text-sm text-gray-800">{{ __('Aucune synchronisation réussie pour l\'instant.') }}</p>
                @endif
                <p class="mt-4 text-sm">
                    <a href="{{ route('agent.donnees-api.index') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Données API') }}</a>
                </p>
            </section>
        </div>
    </div>
</x-app-layout>
