{{-- Le bandeau global est masqué ici : cette page affiche déjà toutes les alertes en cours. --}}
<x-app-layout :title="__('Alertes en cours')" :banniere="false">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Alertes en cours')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Alertes en cours') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @forelse ($alertes as $alerte)
                <article class="alerte {{ $alerte->niveau->classe() }} bg-white shadow-sm p-6" aria-labelledby="alerte-{{ $alerte->id }}">
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-900">{{ $alerte->niveau->label() }}</p>
                    <h2 id="alerte-{{ $alerte->id }}" class="mt-1 text-lg font-semibold text-gray-900">{{ $alerte->titre }}</h2>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ ($alerte->emetteur ?? \App\Enums\Emetteur::Ville)->phrase() }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        @if ($alerte->secteur){{ __('Secteur :') }} {{ $alerte->secteur }} · @endif
                        {{ __('Depuis le') }} {{ \App\Support\DateLocale::format($alerte->starts_at) }}
                        @if ($alerte->ends_at) · {{ __("Jusqu'au") }} {{ \App\Support\DateLocale::format($alerte->ends_at) }} @endif
                    </p>
                    <p class="mt-3 text-sm text-gray-900"><span class="font-medium">{{ __('Ce qui se passe :') }}</span> {{ $alerte->ce_qui_se_passe }}</p>
                    <p class="mt-2 text-sm text-gray-900"><span class="font-medium">{{ __("Ce qu'il faut faire :") }}</span> {{ $alerte->ce_quil_faut_faire }}</p>
                    <p class="mt-3 text-sm">
                        <a href="{{ route('alertes.show', $alerte) }}" class="underline font-medium text-gray-900">{{ __('Voir le détail') }}<span class="sr-only"> {{ __("de l'alerte") }} {{ $alerte->titre }}</span></a>
                    </p>
                </article>
            @empty
                <p class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">{{ __('Aucune alerte en cours.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
