<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Espace agent'), 'url' => route('agent.index')],
            ['label' => __('Centre technique municipal'), 'url' => route('agent.demandes.index')],
            ['label' => $demande->reference],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Demande :reference', ['reference' => $demande->reference]) }}</h1>
    </x-slot>

    @php($suivant = $demande->statut->suivant())

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('succes'))
                <p role="status" class="rounded border-2 border-gray-800 bg-white p-3 text-sm font-medium text-gray-900">{{ session('succes') }}</p>
            @endif
            @if (session('erreur'))
                <p role="alert" class="rounded border-2 border-red-800 bg-white p-3 text-sm font-medium text-red-900">{{ session('erreur') }}</p>
            @endif

            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl p-6 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-lg font-medium text-gray-900">{{ $demande->objet }}</h2>
                    <x-statut-badge :statut="$demande->statut" />
                </div>

                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $demande->message }}</p>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div><dt class="font-medium text-gray-700">{{ __('Demandeur') }}</dt><dd>{{ $demande->nom_demandeur }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Service') }}</dt><dd>{{ $demande->service?->nom ?? ($demande->estImportee() ? __('Non précisé') : __('À orienter')) }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Agent en charge') }}</dt><dd>{{ $demande->agent?->name ?? __('Aucun pour l\'instant') }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Déposée le') }}</dt><dd>{{ \App\Support\DateLocale::format($demande->created_at) }}</dd></div>
                    @if ($demande->traitee_at)
                        <div><dt class="font-medium text-gray-700">{{ __('Traitée le') }}</dt><dd>{{ \App\Support\DateLocale::format($demande->traitee_at) }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">{{ __('Historique') }}</h2>
                <x-frise-demande :demande="$demande" :montrer-agent="true" />

                @can('updateStatus', $demande)
                    <div class="mt-6">
                        @if ($suivant)
                            <form method="POST" action="{{ route('agent.demandes.statut', $demande) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="statut" value="{{ $demande->statut->value }}">
                                <x-primary-button type="submit">
                                    {{ $suivant === \App\Enums\Statut::EnCours ? __('Prendre en charge (passer « En cours »)') : __('Marquer comme traitée') }}
                                </x-primary-button>
                            </form>
                        @else
                            <p class="text-sm text-gray-700">{{ __('Cette demande est traitée : aucune action possible.') }}</p>
                        @endif
                    </div>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
