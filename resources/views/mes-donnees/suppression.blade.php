<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes données'), 'url' => route('mes-donnees.index')],
            ['label' => __('Supprimer mon compte')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Supprimer mon compte') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @error('compte')
                <p role="alert" class="rounded border-2 border-red-800 bg-white p-3 text-sm font-medium text-red-900">{{ $message }}</p>
            @enderror

            <p class="px-4 sm:px-0 text-sm text-gray-800"><strong>{{ __('Étape 1 sur 2') }}</strong> — {{ __('lisez ce qui va se passer. Rien n\'est supprimé tant que vous n\'avez pas confirmé à l\'étape suivante.') }}</p>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="supprime">
                <h2 id="supprime" class="text-lg font-medium text-gray-900">{{ __('Ce qui sera supprimé') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li>{{ __('Votre compte : votre nom, votre adresse e-mail et votre mot de passe.') }}</li>
                    <li>{{ __('Vos préférences d\'affichage.') }}</li>
                    <li>{{ __('Toutes vos connexions ouvertes, sur tous vos appareils.') }}</li>
                    <li>{{ __('Le texte de vos demandes (objet et message) : il est remplacé par « [Contenu supprimé à la demande de l\'habitant] ».') }}</li>
                </ul>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="conserve">
                <h2 id="conserve" class="text-lg font-medium text-gray-900">{{ __('Ce qui sera conservé, sous forme anonyme') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li>{{ __('Vos demandes, sans votre nom ni votre texte : numéro de référence, service, statut, dates et étapes de suivi. Les agents ne pourront plus savoir qui les a faites. La ville les garde pour ses statistiques et la continuité du service.') }}</li>
                    <li>{{ __('Une ligne dans le journal de la ville : « Compte n° … supprimé par son titulaire », avec seulement le numéro du compte.') }}</li>
                </ul>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800 border-2 border-red-800" aria-labelledby="definitif">
                <h2 id="definitif" class="text-lg font-medium text-red-900">{{ __('Cette action est définitive') }}</h2>
                <p>{{ __('Vous ne pourrez plus vous connecter avec ce compte et nous ne pourrons pas le rétablir. Vous pourrez créer un nouveau compte, mais il n\'aura aucun lien avec celui-ci.') }}</p>
                <p>{{ __('Avant de continuer, vous pouvez télécharger vos informations et le récapitulatif de vos demandes :') }}
                    <a href="{{ route('mes-donnees.dossier.telecharger') }}" class="underline">{{ __('mes informations (version imprimable)') }}</a>,
                    <a href="{{ route('demandes.recapitulatif.telecharger') }}" class="underline">{{ __('mes demandes (version imprimable)') }}</a>.</p>
                <p class="flex flex-wrap items-center gap-4 pt-2">
                    <a href="{{ route('mes-donnees.suppression.confirmer') }}" class="inline-flex items-center px-4 py-2 bg-red-800 rounded-md font-semibold text-white hover:bg-red-700">{{ __('Continuer vers la confirmation') }}</a>
                    <a href="{{ route('mes-donnees.index') }}" class="underline text-gray-900">{{ __('Annuler et revenir') }}</a>
                </p>
            </section>
        </div>
    </div>
</x-app-layout>
