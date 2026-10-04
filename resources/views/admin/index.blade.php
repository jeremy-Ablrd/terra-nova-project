<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">Administration</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-alertes-connexion />

            <x-carte>
                <h2 class="text-xl font-semibold text-gray-900">Bienvenue {{ Auth::user()->name }}</h2>
                <p class="mt-2 flex flex-wrap items-center gap-2 text-gray-600">
                    <span class="tn-badge">{{ Auth::user()->role->label() }}</span>
                    {{ __('Gérez les comptes, les services et les alertes de la commune.') }}
                </p>
            </x-carte>

            <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['admin.comptes.index', 'Comptes', 'Gérer les comptes habitants, agents et administrateurs.'],
                    ['admin.services.index', 'Services', 'Gérer le catalogue des services municipaux.'],
                    ['admin.synchronisation.index', 'Synchronisation', 'Lancer et suivre la synchronisation des données.'],
                    ['admin.alertes.index', 'Alertes', 'Publier, modifier et clôturer les alertes.'],
                    ['admin.participation.index', 'Participation', 'Traiter les contributions des habitants et gérer les projets de la ville.'],
                    ['admin.securite', 'Sécurité', 'Consulter les événements de sécurité de la plateforme.'],
                ] as [$route, $titre, $texte])
                    <li class="tn-card flex flex-col gap-3">
                        <h2 class="text-xl font-semibold text-gray-900">{{ __($titre) }}</h2>
                        <p class="text-gray-600">{{ __($texte) }}</p>
                        <p class="mt-auto"><a href="{{ route($route) }}" class="tn-btn tn-btn--secondary">{{ __('Ouvrir') }} {{ \Illuminate\Support\Str::lower(__($titre)) }}</a></p>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-app-layout>
