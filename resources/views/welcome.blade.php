<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ \App\Support\Affichage::classes() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Nova Terra') }}</title>

        <x-tete-assets />
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900 min-h-screen flex flex-col">
        {{-- Premier élément focusable de la page : saute l'en-tête. --}}
        <a href="#contenu" class="skip-link">{{ __('Aller au contenu') }}</a>

        <x-alertes-banniere />

        <header>
            <x-affichage-controles />

            <div class="bg-white border-b border-gray-100">
                <nav aria-label="{{ __('Navigation principale') }}" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 min-h-[4rem] flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold">
                        <x-application-logo class="h-8 w-auto fill-current text-gray-800" />
                        Nova Terra
                    </a>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <a href="{{ route('services.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Services') }}</a>
                        <a href="{{ route('urgences.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Urgences') }}</a>
                        <a href="{{ route('alertes.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Alertes en cours') }}</a>
                        @auth
                            <a href="{{ Auth::user()->homeUrl() }}" class="px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700">Mon espace</a>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2 rounded-md text-gray-700 hover:bg-gray-100">Se connecter</a>
                            <a href="{{ route('register') }}" class="px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700">Créer mon compte</a>
                        @endauth
                    </div>
                </nav>
            </div>
        </header>

        <main id="contenu" tabindex="-1" class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">Bienvenue à Nova Terra</h1>
            <p class="mt-4 text-lg text-gray-600">
                Les services numériques de la ville, accessibles à chaque habitant depuis son espace personnel.
            </p>

            <div class="mt-8">
                @auth
                    <a href="{{ Auth::user()->homeUrl() }}" class="inline-block px-6 py-3 rounded-md bg-gray-800 text-white hover:bg-gray-700">Accéder à mon espace</a>
                @else
                    <a href="{{ route('register') }}" class="inline-block px-6 py-3 rounded-md bg-gray-800 text-white hover:bg-gray-700">Créer mon compte habitant</a>
                    <p class="mt-4 text-sm text-gray-600">
                        Déjà inscrit ? <a href="{{ route('login') }}" class="underline hover:text-gray-900">Se connecter</a>
                    </p>
                @endauth
            </div>
        </main>

        <x-pied-de-page />
    </body>
</html>
