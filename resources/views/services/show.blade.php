<x-app-layout :title="$service->nom">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Services municipaux'), 'url' => route('services.index')],
            ['label' => $service->nom],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ $service->nom }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($service->estIndisponible())
                {{-- En tête, avant tout bouton : l'état, le motif et la prochaine action. --}}
                <div role="status" class="space-y-3">
                    <x-disponibilite-service :service="$service" detail sans-role />
                    <x-prochaine-action :service="$service" :avec-lien-demande="false" class="px-3" />
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @if ($service->organisme)
                    <p class="text-sm font-semibold text-gray-900">{{ $service->organisme }}</p>
                @endif
                <p class="text-sm text-gray-600">
                    {{ $service->categorie->label() }}
                    @if ($service->prioritaire) · <span class="font-semibold text-gray-900">{{ __('Service prioritaire') }}</span> @endif
                </p>

                @unless ($service->estIndisponible())
                    <section aria-labelledby="disponibilite">
                        <h2 id="disponibilite" class="font-medium text-gray-900">{{ __('Disponibilité') }}</h2>
                        <x-disponibilite-service :service="$service" detail class="mt-2" />
                    </section>
                @endunless

                @if ($service->aHorairesStructures())
                    <section aria-labelledby="horaires-ouverture">
                        <h2 id="horaires-ouverture" class="font-medium text-gray-900">{{ __('Horaires d\'ouverture') }}</h2>
                        <x-ouverture-service :service="$service" detail class="mt-2" />
                    </section>
                @endif

                <section aria-labelledby="description">
                    <h2 id="description" class="font-medium text-gray-900">{{ __('Description') }}</h2>
                    <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $service->description }}</p>
                </section>

                <section aria-labelledby="infos-pratiques">
                    <h2 id="infos-pratiques" class="font-medium text-gray-900">{{ __('Informations pratiques') }}</h2>
                    <dl class="mt-2 grid gap-4 sm:grid-cols-3 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('Horaires') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $service->horaires ?? __('Non précisé') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Lieu') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $service->lieu ?? __('Non précisé') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('Contact') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $service->contact ?? __('Non précisé') }}</dd>
                        </div>
                        @if ($service->adresse)
                            <div>
                                <dt class="text-gray-500">{{ __('Adresse') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $service->adresse }}</dd>
                            </div>
                        @endif
                        @if ($service->quartier)
                            <div>
                                <dt class="text-gray-500">{{ __('Quartier') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $service->quartier }}</dd>
                            </div>
                        @endif
                        @if ($service->repere)
                            <div>
                                <dt class="text-gray-500">{{ __('Repère') }}</dt>
                                <dd class="mt-1 text-gray-900">{{ $service->repere }}</dd>
                            </div>
                        @endif
                        @if ($service->telephoneHref())
                            <div>
                                <dt class="text-gray-500">{{ __('Téléphone') }}</dt>
                                <dd class="mt-1 text-gray-900"><x-telephone-lien :service="$service" /></dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>

            <p class="flex flex-wrap items-center gap-4 text-sm">
                @auth
                    @if (Auth::user()->isCitoyen() && ! $service->estDesactive())
                        <x-primary-link href="{{ route('contact.create', ['service_id' => $service->id]) }}">{{ __('Faire une demande à ce service') }}</x-primary-link>
                    @endif
                @else
                    @unless ($service->estDesactive())
                        <a href="{{ route('login') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Se connecter pour faire une demande') }}</a>
                    @endunless
                @endauth
                <a href="{{ route('services.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Retour aux services') }}</a>
            </p>
        </div>
    </div>
</x-app-layout>
