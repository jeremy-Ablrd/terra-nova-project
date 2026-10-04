<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">Administration</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin._subnav')

            <x-alertes-connexion />

            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl p-6">
                <p class="text-lg font-medium text-gray-900">Bienvenue {{ Auth::user()->name }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
