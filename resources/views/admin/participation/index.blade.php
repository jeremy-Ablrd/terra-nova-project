<x-app-layout :title="__('Participation')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Administration'), 'url' => route('admin.index')],
            ['label' => __('Participation')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Participation') }}</h1>
            <x-primary-link href="{{ route('admin.participation.projets.index') }}">{{ __('Gérer les projets') }}</x-primary-link>
        </div>
    </x-slot>

    @php
        $lien = fn ($actif) => 'underline '.($actif ? 'font-bold text-gray-900' : 'text-gray-700 hover:text-gray-900');
    @endphp

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-700">{{ __('Contributions des habitants : avis sur les projets, idées et commentaires sur les services. Elles ne sont jamais affichées publiquement et un avis n\'est pas un vote.') }}</p>

            <nav aria-label="{{ __('Filtrer par statut') }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="font-medium text-gray-900">{{ __('Statut :') }}</span>
                <a href="{{ route('admin.participation.index', array_filter(['type' => $type?->value])) }}" class="{{ $lien($statut === null) }}" @if ($statut === null) aria-current="true" @endif>{{ __('Tous') }}</a>
                @foreach (\App\Enums\StatutContribution::cases() as $s)
                    <a href="{{ route('admin.participation.index', array_filter(['type' => $type?->value, 'statut' => $s->value])) }}" class="{{ $lien($statut === $s) }}" @if ($statut === $s) aria-current="true" @endif>{{ $s->label() }} ({{ $parStatut[$s->value] ?? 0 }})</a>
                @endforeach
            </nav>
            <nav aria-label="{{ __('Filtrer par type') }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="font-medium text-gray-900">{{ __('Type :') }}</span>
                <a href="{{ route('admin.participation.index', array_filter(['statut' => $statut?->value])) }}" class="{{ $lien($type === null) }}" @if ($type === null) aria-current="true" @endif>{{ __('Tous') }}</a>
                @foreach (\App\Enums\TypeContribution::cases() as $t)
                    <a href="{{ route('admin.participation.index', array_filter(['statut' => $statut?->value, 'type' => $t->value])) }}" class="{{ $lien($type === $t) }}" @if ($type === $t) aria-current="true" @endif>{{ $t->label() }}</a>
                @endforeach
            </nav>

            <div class="tn-card overflow-hidden p-0">
                @if ($contributions->isEmpty())
                    <p class="p-6 text-sm text-gray-700">{{ __('Aucune contribution ne correspond à ces filtres.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Contributions, de la plus récente à la plus ancienne') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Référence') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Objet') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Reçue le') }}</th>
                                    <th scope="col" class="px-6 py-3 font-medium">{{ __('Statut') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($contributions as $contribution)
                                    <tr>
                                        <th scope="row" class="px-6 py-3 font-normal"><a href="{{ route('admin.participation.show', $contribution) }}" class="underline tn-code">{{ $contribution->reference }}</a></th>
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
