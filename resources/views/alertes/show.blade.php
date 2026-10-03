<x-app-layout :title="$alerte->titre">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Alertes en cours'), 'url' => route('alertes.index')],
            ['label' => $alerte->titre],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ $alerte->titre }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($terminee)
                <p class="bg-gray-50 border border-gray-300 p-4 text-sm text-gray-900" role="status">
                    <span class="font-medium">{{ __('Alerte terminée') }}</span> — {{ __('cette alerte a pris fin le') }} {{ \App\Support\DateLocale::format($alerte->ends_at) }}.
                </p>
            @endif

            <article class="alerte {{ $alerte->niveau->classe() }} bg-white shadow-sm p-6 space-y-5">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-900">{{ $alerte->niveau->label() }}</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">{{ ($alerte->emetteur ?? \App\Enums\Emetteur::Ville)->phrase() }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        @if ($alerte->secteur){{ __('Secteur :') }} {{ $alerte->secteur }} · @endif
                        {{ __('Depuis le') }} {{ \App\Support\DateLocale::format($alerte->starts_at) }}
                        @if ($alerte->ends_at) · {{ __("Jusqu'au") }} {{ \App\Support\DateLocale::format($alerte->ends_at) }} @endif
                    </p>
                </div>

                <section aria-labelledby="ce-qui-se-passe">
                    <h2 id="ce-qui-se-passe" class="font-medium text-gray-900">{{ __('Ce qui se passe') }}</h2>
                    <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $alerte->ce_qui_se_passe }}</p>
                </section>

                <section aria-labelledby="ce-quil-faut-faire">
                    <h2 id="ce-quil-faut-faire" class="font-medium text-gray-900">{{ __("Ce qu'il faut faire") }}</h2>
                    <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $alerte->ce_quil_faut_faire }}</p>
                </section>

                @if ($alerte->consignes_vulnerables)
                    <section aria-labelledby="consignes-vulnerables">
                        <h2 id="consignes-vulnerables" class="font-medium text-gray-900">{{ __('Consignes pour les personnes vulnérables') }}</h2>
                        <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $alerte->consignes_vulnerables }}</p>
                    </section>
                @endif
            </article>

            <p class="text-sm"><a href="{{ route('alertes.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Retour aux alertes en cours') }}</a></p>
        </div>
    </div>
</x-app-layout>
