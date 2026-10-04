<x-app-layout :title="__('Mes contributions')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes contributions')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Mes contributions') }}</h1>
            <x-primary-link href="{{ route('idees.create') }}">{{ __('Proposer une idée') }}</x-primary-link>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-700">{{ __('Vos avis, idées et commentaires, et ce que la ville en a fait. Ils ne sont pas affichés publiquement et un avis n\'est pas un vote.') }}</p>

            <div class="tn-card overflow-hidden p-0">
                @if ($contributions->isEmpty())
                    <p class="p-6 text-sm text-gray-700">
                        {{ __('Vous n\'avez encore déposé aucune contribution.') }}
                        <a href="{{ route('projets.index') }}" class="underline">{{ __('Voir les projets de la ville') }}</a>
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Mes contributions, de la plus récente à la plus ancienne') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Référence') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Objet') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Envoyée le') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Suivi') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($contributions as $contribution)
                                    <tr>
                                        <th scope="row" class="px-6 py-3 font-normal"><a href="{{ route('mes-contributions.show', $contribution) }}" class="underline tn-code">{{ $contribution->reference }}</a></th>
                                        <td class="px-6 py-3">{{ $contribution->type->label() }}</td>
                                        <td class="px-6 py-3">{{ $contribution->intitule() }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($contribution->created_at) }}</td>
                                        <td class="px-6 py-3"><x-statut-contribution :statut="$contribution->statut" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $contributions->links() }}
        </div>
    </div>
</x-app-layout>
