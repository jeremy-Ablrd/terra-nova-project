<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">Espace agent</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-lg font-medium text-gray-900">Bienvenue {{ Auth::user()->name }}</p>
            </div>

            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="charge-de-travail">
                <h2 id="charge-de-travail" class="font-medium text-gray-900">{{ __('Charge de travail') }}</h2>
                <p class="mt-2 text-3xl font-bold text-gray-900">
                    <x-compteur-en-attente :nombre="$enAttente" visible />
                </p>
                <p class="mt-4 flex flex-wrap items-center gap-4 text-sm">
                    <a href="{{ route('agent.demandes.index', ['statut' => \App\Enums\Statut::Nouvelle->value]) }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Centre technique municipal : demandes en attente') }}</a>
                    <a href="{{ route('agent.demandes.index') }}" class="underline text-gray-700 hover:text-gray-900">{{ __('Centre technique municipal : toutes les demandes') }}</a>
                </p>
            </section>

            <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6" aria-labelledby="suivi-activite">
                <h2 id="suivi-activite" class="font-medium text-gray-900">{{ __('Suivi de l\'activité') }}</h2>
                <p class="mt-2 text-sm text-gray-600">{{ __('Qui a modifié quoi dans l\'administration, et quand.') }}</p>
                <p class="mt-4 text-sm">
                    <a href="{{ route('agent.journal.index') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Journal d\'activité') }}</a>
                </p>
            </section>
        </div>
    </div>
</x-app-layout>
