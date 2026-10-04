<x-app-layout>
    @php($estAgent = Auth::user()->isAgent())

    <x-slot name="breadcrumb">
        <x-breadcrumb :items="$estAgent
            ? [
                ['label' => __('Accueil'), 'url' => url('/')],
                ['label' => __('Espace agent'), 'url' => route('agent.index')],
                ['label' => __('Centre technique municipal'), 'url' => route('agent.demandes.index')],
                ['label' => $demande->reference],
            ]
            : [
                ['label' => __('Accueil'), 'url' => url('/')],
                ['label' => __('Mon espace'), 'url' => route('dashboard')],
                ['label' => __('Mes demandes'), 'url' => route('demandes.index')],
                ['label' => $demande->reference],
            ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">Demande {{ $demande->reference }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if ($estAgent)
                <a href="{{ route('agent.demandes.index') }}" class="text-sm underline text-gray-600 hover:text-gray-900">&larr; {{ __('Retour au Centre technique municipal') }}</a>
            @else
                <a href="{{ route('demandes.index') }}" class="text-sm underline text-gray-600 hover:text-gray-900">&larr; {{ __('Retour à mes demandes') }}</a>
            @endif

            <div class="tn-card overflow-hidden p-6">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ $demande->objet }}</h3>
                    <x-statut-badge :statut="$demande->statut" />
                </div>
                <p class="mt-1 text-sm text-gray-500">Déposée le {{ \App\Support\DateLocale::format($demande->created_at) }}</p>
                <p class="mt-4 text-sm text-gray-800 whitespace-pre-line">{{ $demande->message }}</p>
            </div>

            <div class="tn-card overflow-hidden p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">{{ __('Suivi de votre demande') }}</h2>
                <p class="text-sm text-gray-700 mb-3">{{ __('État actuel : :statut', ['statut' => $demande->statut->label()]) }}</p>
                <x-frise-demande :demande="$demande" />
            </div>

            @can('accuserReception', $demande)
                <p><a href="{{ route('demandes.accuse', $demande) }}" class="tn-btn tn-btn--secondary">{{ __('Accusé de réception') }}</a></p>
            @endcan
        </div>
    </div>
</x-app-layout>
