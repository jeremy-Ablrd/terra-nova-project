{{-- Le bandeau global est masqué ici : cette page affiche déjà toutes les alertes en cours. --}}
<x-app-layout :title="__('Alertes en cours')" :banniere="false" public>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Alertes en cours')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Alertes en cours') }}</h1>
            @if ($alertes->isNotEmpty())
                @php($plusHaut = $alertes->first()->niveau)
                <span class="tn-badge {{ $plusHaut->badge() }}" data-glyphe="{{ $plusHaut->glyphe() }}">{{ trans_choice(':count active|:count actives', $alertes->count()) }}</span>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8">
            @if ($alertes->isEmpty())
                <x-carte class="text-sm text-gray-600">
                    <p>{{ __('Aucune alerte en cours.') }}</p>
                </x-carte>
            @else
                <ul class="grid gap-6 md:grid-cols-2">
                    @foreach ($alertes as $alerte)
                        <li>
                            <article class="tn-card h-full flex flex-col gap-3" aria-labelledby="alerte-{{ $alerte->id }}">
                                <p><span class="tn-badge {{ $alerte->niveau->badge() }}" data-glyphe="{{ $alerte->niveau->glyphe() }}">{{ $alerte->niveau->label() }}</span></p>
                                <h2 id="alerte-{{ $alerte->id }}" class="text-xl font-semibold text-gray-900">{{ $alerte->titre }}</h2>
                                <p class="text-sm font-semibold text-gray-900">{{ ($alerte->emetteur ?? \App\Enums\Emetteur::Ville)->phrase() }}</p>
                                <p class="text-sm text-gray-600">
                                    @if ($alerte->secteur){{ $alerte->secteur }} · @endif
                                    {{ __('Depuis le') }} {{ \App\Support\DateLocale::format($alerte->starts_at) }}
                                    @if ($alerte->ends_at) · {{ __("Jusqu'au") }} {{ \App\Support\DateLocale::format($alerte->ends_at) }} @endif
                                </p>
                                <p class="text-sm text-gray-900"><span class="font-semibold">{{ __('Ce qui se passe :') }}</span> {{ $alerte->ce_qui_se_passe }}</p>
                                <p class="text-sm text-gray-900"><span class="font-semibold">{{ __("Ce qu'il faut faire :") }}</span> {{ $alerte->ce_quil_faut_faire }}</p>
                                <p class="mt-auto pt-2">
                                    <a href="{{ route('alertes.show', $alerte) }}" class="tn-btn tn-btn--primary">{{ __('Voir le détail') }}<span class="sr-only"> {{ __("de l'alerte") }} {{ $alerte->titre }}</span></a>
                                </p>
                            </article>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
