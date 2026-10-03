<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Demande {{ $demande->reference }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <a href="{{ route('demandes.index') }}" class="text-sm underline text-gray-600 hover:text-gray-900">&larr; Retour à mes demandes</a>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ $demande->objet }}</h3>
                    <x-statut-badge :statut="$demande->statut" />
                </div>
                <p class="mt-1 text-sm text-gray-500">Déposée le {{ \App\Support\DateLocale::format($demande->created_at) }}</p>
                <p class="mt-4 text-sm text-gray-800 whitespace-pre-line">{{ $demande->message }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
