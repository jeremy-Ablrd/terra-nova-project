<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Compte supprimé')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Votre compte a été supprimé') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <section class="tn-card p-6 space-y-3 text-sm text-gray-800" aria-labelledby="bilan">
                <h2 id="bilan" class="text-lg font-medium text-gray-900">{{ __('Ce qui a été fait') }}</h2>
                <p>{{ __('Votre compte, vos préférences et vos connexions ont été supprimés. Vous êtes déconnecté.') }}</p>
                <h2 class="text-lg font-medium text-gray-900">{{ __('Ce qui a été conservé') }}</h2>
                <p>{{ __('Vos demandes ont été conservées sous forme anonyme : sans votre nom ni votre texte, avec seulement leur numéro de référence, leur service, leur statut, leurs dates et leurs étapes. Une ligne du journal de la ville indique « Compte n° … supprimé par son titulaire », avec le numéro du compte seulement.') }}</p>
                <p class="pt-2"><a href="{{ url('/') }}" class="underline font-medium text-gray-900">{{ __('Retour à l\'accueil') }}</a></p>
            </section>
        </div>
    </div>
</x-app-layout>
