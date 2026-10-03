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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
