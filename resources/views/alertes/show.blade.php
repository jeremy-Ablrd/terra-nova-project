<x-app-layout :title="$alerte->titre">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Alertes en cours'), 'url' => route('alertes.index')],
            ['label' => $alerte->titre],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ $alerte->titre }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($terminee)
                <p class="tn-banner tn-banner--info max-w-none" role="status">
                    <span class="font-medium">{{ __('Alerte terminée') }}</span> — {{ __('cette alerte a pris fin le') }} {{ \App\Support\DateLocale::format($alerte->ends_at) }}.
                </p>
            @endif

            <article class="tn-card space-y-5 p-6">
                <div>
                    <p><span class="tn-badge {{ $alerte->niveau->badge() }}" data-glyphe="{{ $alerte->niveau->glyphe() }}">{{ $alerte->niveau->label() }}</span></p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ ($alerte->emetteur ?? \App\Enums\Emetteur::Ville)->phrase() }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        @if ($alerte->secteur){{ __('Secteur :') }} {{ $alerte->secteur }} · @endif
                        {{ __('Depuis le') }} {{ \App\Support\DateLocale::format($alerte->starts_at) }}
                        @if ($alerte->ends_at) · {{ __("Jusqu'au") }} {{ \App\Support\DateLocale::format($alerte->ends_at) }} @endif
                    </p>
                </div>

                <section aria-labelledby="ce-qui-se-passe">
                    <h2 id="ce-qui-se-passe" class="text-lg font-semibold text-gray-900">{{ __('Ce qui se passe') }}</h2>
                    <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $alerte->ce_qui_se_passe }}</p>
                </section>

                <section aria-labelledby="ce-quil-faut-faire">
                    <h2 id="ce-quil-faut-faire" class="text-lg font-semibold text-gray-900">{{ __("Ce qu'il faut faire") }}</h2>
                    <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $alerte->ce_quil_faut_faire }}</p>
                </section>

                @if ($alerte->consignes_vulnerables)
                    <section aria-labelledby="consignes-vulnerables">
                        <h2 id="consignes-vulnerables" class="text-lg font-semibold text-gray-900">{{ __('Consignes pour les personnes vulnérables') }}</h2>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $alerte->consignes_vulnerables }}</p>
                    </section>
                @endif
            </article>

            <p class="text-sm"><a href="{{ route('alertes.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Retour aux alertes en cours') }}</a></p>
        </div>
    </div>
</x-app-layout>
