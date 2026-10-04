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

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('succes'))
                <p role="status" class="rounded border-2 border-gray-800 bg-white p-3 text-sm font-medium text-gray-900">{{ session('succes') }}</p>
            @endif
            @if (session('erreur'))
                <p role="alert" class="rounded border-2 border-red-800 bg-white p-3 text-sm font-medium text-red-900">{{ session('erreur') }}</p>
            @endif

            <div class="tn-card overflow-hidden p-6 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-lg font-medium text-gray-900">{{ $demande->objet }}</h2>
                    <span class="flex flex-wrap items-center gap-2">
                        <x-badge-priorite :priorite="$demande->priorite" />
                        <x-statut-badge :statut="$demande->statut" />
                    </span>
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

            @can('changerPriorite', $demande)
                @php($actuelle = $demande->priorite ?? \App\Enums\Priorite::Normale)
                <section class="tn-card overflow-hidden p-6 space-y-3" aria-labelledby="priorite-titre">
                    <h2 id="priorite-titre" class="text-lg font-medium text-gray-900">{{ __('Priorité') }}</h2>
                    <p class="text-sm text-gray-800">{{ __('Priorité actuelle :') }} <strong>{{ $actuelle->label() }}</strong></p>
                    <div class="flex flex-wrap gap-3">
                        @foreach (\App\Enums\Priorite::cases() as $niveau)
                            @if ($niveau !== $actuelle)
                                <form method="POST" action="{{ route('agent.demandes.priorite', $demande) }}">
                                    @csrf
                                    <input type="hidden" name="priorite" value="{{ $niveau->value }}">
                                    <input type="hidden" name="priorite_affichee" value="{{ $actuelle->value }}">
                                    <x-secondary-button type="submit">{{ __('Passer en « :niveau »', ['niveau' => $niveau->label()]) }}</x-secondary-button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endcan

            <section class="tn-card overflow-hidden p-6 space-y-3" aria-labelledby="reponse-titre">
                <h2 id="reponse-titre" class="text-lg font-medium text-gray-900">{{ __('Réponse à l\'habitant') }}</h2>
                @can('repondre', $demande)
                    <form method="POST" action="{{ route('agent.demandes.reponse', $demande) }}" class="space-y-3" novalidate>
                        @csrf
                        <div>
                            <x-input-label for="reponse" :value="__('Votre réponse').' *'" />
                            <textarea id="reponse" name="reponse" rows="5" minlength="5" maxlength="2000" required aria-required="true"
                                      aria-describedby="reponse_aide{{ $errors->has('reponse') ? ' reponse_erreur' : '' }}"
                                      aria-invalid="{{ $errors->has('reponse') ? 'true' : 'false' }}"
                                      class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('reponse') }}</textarea>
                            <p id="reponse_aide" class="mt-1 text-xs text-gray-500">{{ __('Entre 5 et 2 000 caractères. L\'habitant est averti sur son espace ; votre nom ne lui est pas communiqué.') }}</p>
                            <x-input-error id="reponse_erreur" :messages="$errors->get('reponse')" class="mt-2" role="alert" />
                        </div>
                        <x-primary-button type="submit">{{ __('Envoyer la réponse') }}</x-primary-button>
                    </form>
                @else
                    <p class="text-sm text-gray-700">{{ __('Cette demande n\'a pas de compte habitant destinataire (demande importée de l\'API, ou compte supprimé) : la réponse n\'est pas possible depuis la plateforme.') }}</p>
                @endcan
            </section>
            <div class="tn-card overflow-hidden p-6">
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
