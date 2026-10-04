<x-app-layout :title="__('Contribution :reference', ['reference' => $contribution->reference])">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes contributions'), 'url' => route('mes-contributions.index')],
            ['label' => $contribution->reference],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Contribution :reference', ['reference' => $contribution->reference]) }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('mes-contributions.index') }}" class="text-sm underline text-gray-700 hover:text-gray-900">&larr; {{ __('Retour à mes contributions') }}</a>

            @if ($errors->has('participation'))
                <p role="alert" class="tn-banner tn-banner--warn max-w-none"><span aria-hidden="true">▲</span> {{ $errors->first('participation') }}</p>
            @endif

            <div class="tn-card p-6 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h2 class="text-lg font-medium text-gray-900">{{ $contribution->type->label() }} — {{ $contribution->intitule() }}</h2>
                    <x-statut-contribution :statut="$contribution->statut" />
                </div>
                <p class="text-sm text-gray-600">{{ __('Envoyée le :date', ['date' => \App\Support\DateLocale::format($contribution->created_at)]) }}</p>
                @if ($contribution->projet)
                    <p class="text-sm"><a href="{{ route('projets.show', $contribution->projet) }}" class="underline">{{ __('Voir le projet') }}</a></p>
                @elseif ($contribution->service)
                    <p class="text-sm"><a href="{{ route('services.show', $contribution->service) }}" class="underline">{{ __('Voir le service') }}</a></p>
                @endif
                <p class="text-gray-900 whitespace-pre-line">{{ $contribution->message }}</p>
                <p class="text-sm text-gray-600">{{ __('Votre contribution n\'est pas affichée publiquement.') }}
                    @if ($contribution->type === \App\Enums\TypeContribution::Avis) {{ __('Un avis n\'est pas un vote : il n\'est pas compté.') }} @endif</p>
            </div>

            <section aria-labelledby="suivi" class="tn-card p-6 space-y-4">
                <h2 id="suivi" class="text-lg font-semibold text-gray-900">{{ __('Suivi de votre contribution') }}</h2>
                <x-frise-contribution :contribution="$contribution" />

                @if ($contribution->reponse)
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="font-medium text-gray-900">{{ __('Réponse de la ville') }}</h3>
                        <p class="text-sm text-gray-600">{{ __('Le :date', ['date' => \App\Support\DateLocale::format($contribution->reponse_at)]) }}</p>
                        <p class="mt-1 text-gray-900 whitespace-pre-line">{{ $contribution->reponse }}</p>
                    </div>
                @elseif ($contribution->statut !== \App\Enums\StatutContribution::PriseEnCompte)
                    <p class="text-sm text-gray-700">{{ __('La ville n\'a pas encore répondu.') }}</p>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
