<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Synchronisation des demandes') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin._subnav')

            @if ($resultat)
                @if ($resultat['ok'] === null)
                    <div role="status" class="rounded-md bg-gray-50 border border-gray-300 p-4 text-sm text-gray-900">
                        <p class="font-medium">{{ __('Synchronisation déjà en cours') }}</p>
                        <p class="mt-1">{{ $resultat['message'] }}</p>
                    </div>
                @elseif ($resultat['ok'])
                    <div role="status" class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-900">
                        <p class="font-medium">{{ __('Synchronisation réussie') }}</p>
                        <p class="mt-1">{{ $resultat['message'] }}</p>
                    </div>
                @else
                    <div role="alert" class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-900">
                        <p class="font-medium">{{ __('Échec de la synchronisation') }}</p>
                        <p class="mt-1">{{ $resultat['message'] }}</p>
                        <p class="mt-1">{{ __('Les demandes déjà enregistrées sont conservées.') }}</p>
                    </div>
                @endif
            @endif

            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="etat-synchro">
                <h2 id="etat-synchro" class="font-medium text-gray-900">{{ __('État de la synchronisation') }}</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('Dernière synchronisation réussie') }}</dt>
                        <dd class="mt-1 text-gray-900">
                            @if ($lastSync)
                                {{ \App\Support\DateLocale::format($lastSync) }}
                                @if ($lastResult)
                                    <span class="block text-gray-600">{{ __(':received demandes reçues, :new nouvelles.', $lastResult) }}</span>
                                @endif
                            @else
                                {{ __('Jamais') }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Dernière erreur') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $lastError ?: __('Aucune') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Demandes enregistrées en base') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $total }}</dd>
                    </div>
                </dl>
            </section>

            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="etat-session">
                <h2 id="etat-session" class="font-medium text-gray-900">{{ __('Session de l\'API') }}</h2>
                @if ($session)
                    <dl class="mt-4 grid gap-4 sm:grid-cols-3 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('Vague actuelle') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $session['current_wave'] ?? __('Inconnue') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Prochaine vague dans') }}</dt>
                            <dd class="mt-1 text-gray-900">
                                {{ isset($session['minutes_until_next_wave']) ? $session['minutes_until_next_wave'].' '.__('min') : __('Inconnu') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Demandes visibles') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $session['visible_requests_count'] ?? __('Inconnu') }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-gray-500">{{ __('Valeurs relevées lors de la dernière synchronisation réussie.') }}</p>
                @else
                    <p class="mt-4 text-sm text-gray-600">{{ __('Aucune information de session : lancez une première synchronisation.') }}</p>
                @endif
            </section>

            <form method="POST" action="{{ route('admin.synchronisation.run') }}"
                  x-data="{ envoi: false }" x-on:submit="envoi = true" x-on:pageshow.window="envoi = false">
                @csrf
                <x-primary-button x-bind:disabled="envoi" x-bind:aria-disabled="envoi" class="disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-text="envoi ? {{ Js::from(__('Synchronisation en cours…')) }} : {{ Js::from(__('Actualiser maintenant')) }}">{{ __('Actualiser maintenant') }}</span>
                </x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
