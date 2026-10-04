<x-app-layout :title="__('Alertes')">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Alertes') }}</h1>
            <x-primary-link href="{{ route('admin.alertes.create') }}">{{ __('Nouvelle alerte') }}</x-primary-link>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="tn-card overflow-hidden p-0">
                @if ($alertes->isEmpty())
                    <p class="p-6 text-sm text-gray-600">{{ __("Aucune alerte pour le moment. Publiez la première avec « Nouvelle alerte ».") }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Alertes, de la plus récente à la plus ancienne') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-500">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Titre') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Niveau') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Secteur') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Début') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Fin') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('État') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($alertes as $alerte)
                                    @php($etat = $alerte->etat())
                                    <tr>
                                        <td class="px-6 py-3">{{ $alerte->titre }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap font-medium">{{ $alerte->niveau->label() }}</td>
                                        <td class="px-6 py-3">{{ $alerte->secteur ?? '—' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ \App\Support\DateLocale::format($alerte->starts_at) }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">{{ $alerte->ends_at ? \App\Support\DateLocale::format($alerte->ends_at) : '—' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            {{ match ($etat) { \App\Models\Alerte::ETAT_ACTIVE => __('Active'), \App\Models\Alerte::ETAT_PROGRAMMEE => __('Programmée'), default => __('Terminée') } }}
                                        </td>
                                        <td class="px-6 py-3">
                                            @if ($etat !== \App\Models\Alerte::ETAT_TERMINEE)
                                                <form method="POST" action="{{ route('admin.alertes.terminer', $alerte) }}">
                                                    @csrf
                                                    <x-secondary-button type="submit">
                                                        {{ $etat === \App\Models\Alerte::ETAT_ACTIVE ? __('Terminer maintenant') : __('Annuler la programmation') }}
                                                        <span class="sr-only"> — {{ $alerte->titre }}</span>
                                                    </x-secondary-button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $alertes->links() }}
        </div>
    </div>
</x-app-layout>
