<x-app-layout :title="__('Projets de la ville')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Administration'), 'url' => route('admin.index')],
            ['label' => __('Participation'), 'url' => route('admin.participation.index')],
            ['label' => __('Projets')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Projets de la ville') }}</h1>
            <x-primary-link href="{{ route('admin.participation.projets.create') }}">{{ __('Nouveau projet') }}</x-primary-link>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="tn-card overflow-hidden p-0">
                @if ($projets->isEmpty())
                    <p class="p-6 text-sm text-gray-700">{{ __('Aucun projet pour le moment. Créez le premier avec « Nouveau projet ».') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Projets, du plus récent au plus ancien') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Titre') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Publication') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Consultation') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($projets as $projet)
                                    <tr>
                                        <th scope="row" class="px-6 py-3 font-normal">{{ $projet->titre }}</th>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            @if ($projet->estPublie())
                                                <span class="tn-badge tn-badge--success"><span aria-hidden="true">✓</span>{{ __('Publié') }}</span>
                                            @else
                                                <span class="tn-badge"><span aria-hidden="true">○</span>{{ __('Brouillon') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3"><x-etat-consultation :projet="$projet" /></td>
                                        <td class="px-6 py-3">
                                            <a href="{{ route('admin.participation.projets.edit', $projet) }}" class="tn-btn tn-btn--secondary">{{ __('Modifier') }}<span class="sr-only"> : {{ $projet->titre }}</span></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{ $projets->links() }}
        </div>
    </div>
</x-app-layout>
