<x-app-layout :title="__('Accusé de réception')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes demandes'), 'url' => route('demandes.index')],
            ['label' => $a['reference'], 'url' => route('demandes.show', $a['demande'])],
            ['label' => __('Accusé de réception')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Accusé de réception') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            {{-- Impression : le bouton n'apparaît qu'avec JavaScript ; sans lui, la consigne écrite suffit. --}}
            <div class="no-print px-4 sm:px-0 flex flex-wrap items-center gap-4 text-sm text-gray-800">
                <a href="{{ route('demandes.accuse.telecharger', $a['demande']) }}" class="tn-btn tn-btn--primary">{{ __('Télécharger l\'accusé de réception (version imprimable)') }}</a>
                <button type="button" hidden x-data x-init="$el.hidden = false" x-on:click="window.print()"
                        class="tn-btn tn-btn--primary">{{ __('Imprimer') }}</button>
                <span>{{ __('Pour imprimer sans bouton : touches Ctrl + P (⌘ + P sur Mac).') }}</span>
                <a href="{{ route('demandes.show', $a['demande']) }}" class="underline">{{ __('Retour à ma demande') }}</a>
            </div>

            <article class="document tn-card p-6">
                <p class="sous-titre">{{ __('Demande :reference auprès de la ville de Terra Nova.', ['reference' => $a['reference']]) }}</p>
                @include('demandes._accuse')
            </article>
        </div>
    </div>
</x-app-layout>
