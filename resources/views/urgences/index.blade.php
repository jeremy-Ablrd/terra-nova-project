<x-app-layout :title="__('Urgences et hôpitaux')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Urgences et hôpitaux')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Urgences et hôpitaux') }}</h1>
        <p class="mt-2 max-w-2xl text-gray-600">{{ __('Les services d\'urgence et de santé de Terra Nova : où ils se trouvent, comment les joindre et s\'ils sont disponibles.') }}</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($services->isEmpty())
                <x-carte class="text-sm text-gray-600">
                    <p>{{ __('Aucun service d\'urgence ou de santé n\'est renseigné pour le moment.') }}</p>
                </x-carte>
            @else
                <ul class="grid gap-6 md:grid-cols-2">
                    @foreach ($services as $service)
                        <li>
                            <article aria-labelledby="urgence-{{ $service->id }}" class="tn-card h-full flex flex-col gap-4">
                                <div>
                                    <h2 id="urgence-{{ $service->id }}" class="text-xl font-semibold text-gray-900">{{ $service->nom }}</h2>
                                    <p class="mt-1 text-sm text-gray-600">
                                        {{ $service->categorie->label() }}
                                        @if ($service->urgence) · <span class="font-semibold text-red-900"><span aria-hidden="true">! </span>{{ __('Service d\'urgence') }}</span> @endif
                                    </p>
                                </div>

                                {{-- Disponibilité d'abord, avec motif, retour et alternative si le service est interrompu. --}}
                                <x-disponibilite-service :service="$service" detail />
                                <x-prochaine-action :service="$service" />

                                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-gray-600">{{ __('Adresse') }}</dt>
                                        <dd class="mt-0.5 font-medium text-gray-900">{{ $service->adresseAffichee() ?? __('Non précisée') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-600">{{ __('Quartier') }}</dt>
                                        <dd class="mt-0.5 font-medium text-gray-900">{{ $service->quartier ?? __('Non précisé') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-600">{{ __('Repère') }}</dt>
                                        <dd class="mt-0.5 font-medium text-gray-900">{{ $service->repere ?? __('Non précisé') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-600">{{ __('Téléphone') }}</dt>
                                        <dd class="mt-0.5 font-medium text-gray-900">
                                            @if ($service->telephoneHref())
                                                <x-telephone-lien :service="$service" />
                                            @else
                                                {{ __('Non précisé') }}
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-gray-600">{{ __('Horaires') }}</dt>
                                        <dd class="mt-0.5 font-medium text-gray-900">{{ $service->horaires ?? __('Non précisé') }}</dd>
                                    </div>
                                </dl>

                                @if ($service->telephoneHref())
                                    <p class="mt-auto">
                                        <a href="{{ $service->telephoneHref() }}" class="tn-btn tn-btn--primary"
                                           aria-label="{{ __('Appeler') }} {{ $service->nom }} {{ __('au') }} {{ $service->telephone }}">{{ __('Appeler le') }} {{ $service->telephone }}</a>
                                    </p>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
