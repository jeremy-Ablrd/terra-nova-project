<x-app-layout :title="__('Services')">
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Services') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin._subnav')

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <caption class="sr-only">{{ __('Services municipaux, prioritaires en premier') }}</caption>
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Service') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Catégorie') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Prioritaire') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Disponibilité') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Retour estimé') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('Coupure d\'urgence') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($services as $service)
                                <tr>
                                    <td class="px-6 py-3">{{ $service->nom }}</td>
                                    <td class="px-6 py-3">{{ $service->categorie->label() }}</td>
                                    <td class="px-6 py-3">{{ $service->prioritaire ? __('Oui') : __('Non') }}</td>
                                    <td class="px-6 py-3 font-medium">{{ $service->disponibilite->label() }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap">{{ $service->retour_estime_at ? \App\Support\DateLocale::format($service->retour_estime_at) : '—' }}</td>
                                    <td class="px-6 py-3">
                                        <a href="{{ route('admin.services.edit', $service) }}" class="underline text-gray-900 hover:text-gray-600">{{ __('Modifier') }}<span class="sr-only"> {{ $service->nom }}</span></a>
                                    </td>
                                    <td class="px-6 py-3 align-top">
                                        @if ($service->estDesactive())
                                            {{-- Un clic : remise en service (efface motif et alternative). --}}
                                            <form method="POST" action="{{ route('admin.services.reactiver', $service) }}">
                                                @csrf
                                                <x-secondary-button type="submit">{{ __('Réactiver') }}<span class="sr-only"> {{ $service->nom }}</span></x-secondary-button>
                                            </form>
                                        @else
                                            {{-- Désactiver : un motif (obligatoire) et une alternative (facultative), puis un clic. Sans JavaScript. --}}
                                            <details>
                                                <summary class="cursor-pointer underline font-medium text-red-900">{{ __('Désactiver') }}<span class="sr-only"> {{ $service->nom }}</span></summary>
                                                <form method="POST" action="{{ route('admin.services.desactiver', $service) }}" class="mt-2 space-y-2 w-64">
                                                    @csrf
                                                    <div>
                                                        <label for="motif-{{ $service->id }}" class="block text-xs font-medium text-gray-700">{{ __('Motif (obligatoire)') }}</label>
                                                        <input id="motif-{{ $service->id }}" name="motif_interruption" type="text" required maxlength="500" aria-required="true"
                                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                    </div>
                                                    <div>
                                                        <label for="alternative-{{ $service->id }}" class="block text-xs font-medium text-gray-700">{{ __('Quoi faire à la place (facultatif)') }}</label>
                                                        <input id="alternative-{{ $service->id }}" name="alternative" type="text" maxlength="500"
                                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                                    </div>
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">{{ __('Désactiver') }}<span class="sr-only"> {{ $service->nom }}</span></button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
