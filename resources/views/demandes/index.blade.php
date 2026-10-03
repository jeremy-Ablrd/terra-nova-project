<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">Mes demandes</h1>
            @if (Auth::user()->isCitoyen())
                <x-primary-link href="{{ route('contact.create') }}">{{ __('Nouvelle demande') }}</x-primary-link>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @if ($demandes->isEmpty())
                    <p class="p-6 text-sm text-gray-600">Vous n'avez encore aucune demande.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th class="px-6 py-3 font-medium">Référence</th>
                                    <th class="px-6 py-3 font-medium">Objet</th>
                                    <th class="px-6 py-3 font-medium">Date</th>
                                    <th class="px-6 py-3 font-medium">Statut</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($demandes as $demande)
                                    <tr>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <a href="{{ route('demandes.show', $demande) }}" class="underline text-gray-900 hover:text-gray-600">{{ $demande->reference }}</a>
                                        </td>
                                        <td class="px-6 py-3">{{ $demande->objet }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($demande->created_at) }}</td>
                                        <td class="px-6 py-3"><x-statut-badge :statut="$demande->statut" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
