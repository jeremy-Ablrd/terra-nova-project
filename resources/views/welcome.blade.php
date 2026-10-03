<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Nova Terra') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <x-alertes-banniere />

        <header class="bg-white border-b border-gray-100">
            <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold">
                    <x-application-logo class="h-8 w-auto fill-current text-gray-800" />
                    Nova Terra
                </a>

                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('services.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Services') }}</a>
                    <a href="{{ route('alertes.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Alertes en cours') }}</a>
                    @auth
                        <a href="{{ Auth::user()->homeUrl() }}" class="px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700">Mon espace</a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 rounded-md text-gray-700 hover:bg-gray-100">Se connecter</a>
                        <a href="{{ route('register') }}" class="px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700">Créer mon compte</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
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
    </body>
</html>
