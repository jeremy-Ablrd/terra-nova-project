<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes données')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Mes données') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-6 text-sm text-gray-800" aria-labelledby="documents">
                <h2 id="documents" class="text-lg font-medium text-gray-900">{{ __('Vos deux documents') }}</h2>
                <p>{{ __('Ce sont des documents faits pour être lus, imprimés ou conservés : des phrases, des tableaux et des explications. Chacun existe en version imprimable, à lire à l\'écran, à imprimer ou à télécharger.') }}</p>

                <div class="space-y-2">
                    <h3 class="font-medium text-gray-900">{{ __('Mes informations') }}</h3>
                    <p>{{ __('Qui vous êtes pour la ville, ce que la ville conserve sur vous, pourquoi et combien de temps, votre activité (nombre de demandes, délai moyen de traitement), vos préférences et ce que vous pouvez faire de vos données.') }}</p>
                    <p class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('mes-donnees.dossier.telecharger') }}" class="tn-btn tn-btn--primary">{{ __('Télécharger mes informations (version imprimable)') }}</a>
                        <a href="{{ route('mes-donnees.dossier') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Lire et imprimer mes informations') }}</a>
                    </p>
                </div>

                <div class="space-y-2">
                    <h3 class="font-medium text-gray-900">{{ __('Récapitulatif de mes demandes') }}</h3>
                    <p>{{ __('Une synthèse en phrases, le tableau de toutes vos demandes avec ce que chaque statut signifie pour vous, puis ce qui s\'est passé pour chacune.') }}</p>
                    <p class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('demandes.recapitulatif.telecharger') }}" class="tn-btn tn-btn--primary">{{ __('Télécharger le récapitulatif de mes demandes (version imprimable)') }}</a>
                        <a href="{{ route('demandes.recapitulatif') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Lire et imprimer le récapitulatif') }}</a>
                    </p>
                </div>
            </section>

            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-3 text-sm text-gray-800" aria-labelledby="gerer">
                <h2 id="gerer" class="text-lg font-medium text-gray-900">{{ __('Gérer mes données') }}</h2>
                <ul class="space-y-3">
                    <li>
                        <a href="{{ route('mes-donnees.suppression') }}" class="underline font-medium text-red-900 hover:text-red-700">{{ __('Supprimer mon compte') }}</a>
                        <p class="text-gray-700">{{ __('Vous verrez d\'abord ce qui sera supprimé et ce qui sera conservé. Rien n\'est supprimé avant votre confirmation.') }}</p>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
