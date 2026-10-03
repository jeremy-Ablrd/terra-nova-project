<x-app-layout :title="__('Urgences et hôpitaux')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Urgences et hôpitaux')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Urgences et hôpitaux') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-700">{{ __('Les services d\'urgence et de santé de Nova Terra : où ils se trouvent, comment les joindre et s\'ils sont disponibles.') }}</p>

            @if ($services->isEmpty())
                <p class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">{{ __('Aucun service d\'urgence ou de santé n\'est renseigné pour le moment.') }}</p>
            @else
                <ul class="grid gap-6 md:grid-cols-2">
                    @foreach ($services as $service)
                        <li>
                            <article aria-labelledby="urgence-{{ $service->id }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 h-full">
                                <div>
                                    <h2 id="urgence-{{ $service->id }}" class="text-lg font-semibold text-gray-900">{{ $service->nom }}</h2>
                                    <p class="mt-1 text-xs text-gray-600">
                                        {{ $service->categorie->label() }}
                                        @if ($service->urgence) · <span class="font-semibold text-gray-900">{{ __('Service d\'urgence') }}</span> @endif
                                    </p>
                                </div>

                                {{-- Disponibilité d'abord, avec motif, retour et alternative si le service est interrompu. --}}
                                <x-disponibilite-service :service="$service" detail />
                                <x-prochaine-action :service="$service" />

                                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-gray-500">{{ __('Adresse') }}</dt>
                                        <dd class="mt-0.5 text-gray-900">{{ $service->adresseAffichee() ?? __('Non précisée') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">{{ __('Quartier') }}</dt>
                                        <dd class="mt-0.5 text-gray-900">{{ $service->quartier ?? __('Non précisé') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">{{ __('Repère') }}</dt>
                                        <dd class="mt-0.5 text-gray-900">{{ $service->repere ?? __('Non précisé') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">{{ __('Téléphone') }}</dt>
                                        <dd class="mt-0.5 text-gray-900">
                                            @if ($service->telephoneHref())
                                                <x-telephone-lien :service="$service" />
                                            @else
                                                {{ __('Non précisé') }}
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-gray-500">{{ __('Horaires') }}</dt>
                                        <dd class="mt-0.5 text-gray-900">{{ $service->horaires ?? __('Non précisé') }}</dd>
                                    </div>
                                </dl>
                            </article>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
