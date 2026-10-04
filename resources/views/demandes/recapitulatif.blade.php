<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes données'), 'url' => route('mes-donnees.index')],
            ['label' => __('Récapitulatif de mes demandes')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Récapitulatif de mes demandes') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            {{-- Impression : le bouton n'apparaît qu'avec JavaScript ; sans lui, la consigne écrite suffit. --}}
            <div class="no-print px-4 sm:px-0 flex flex-wrap items-center gap-4 text-sm text-gray-800">
                <a href="{{ route('demandes.recapitulatif.telecharger') }}" class="tn-btn tn-btn--primary">{{ __('Télécharger le récapitulatif de mes demandes (version imprimable)') }}</a>
                <button type="button" hidden x-data x-init="$el.hidden = false" x-on:click="window.print()"
                        class="tn-btn tn-btn--primary">{{ __('Imprimer') }}</button>
                <span>{{ __('Pour imprimer sans bouton : touches Ctrl + P (⌘ + P sur Mac).') }}</span>
                <a href="{{ route('mes-donnees.index') }}" class="underline">{{ __('Retour à mes données') }}</a>
            </div>

            <article class="document bg-white border border-gray-200 rounded-xl p-6">
                <p class="sous-titre">{{ __('Où en sont vos demandes auprès de la ville de Terra Nova.') }}</p>
                @include('demandes._recapitulatif')
            </article>
        </div>
    </div>
</x-app-layout>
