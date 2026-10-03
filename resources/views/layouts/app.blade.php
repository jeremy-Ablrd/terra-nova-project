<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ \App\Support\Affichage::classes() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' – ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{-- Premier élément focusable de la page : saute l'en-tête et la navigation. --}}
        <a href="#contenu" class="skip-link">{{ __('Aller au contenu') }}</a>

        @if ($banniere)
            <x-alertes-banniere />
        @endif

        <div class="min-h-screen flex flex-col bg-gray-100">
            <header>
                <x-affichage-controles />

                @auth
                    @include('layouts.navigation')
                @else
                    @include('layouts.invite')
                @endauth
            </header>

            <!-- Fil d'Ariane (optionnel) -->
            @isset($breadcrumb)
                {{ $breadcrumb }}
            @endisset

            <main id="contenu" tabindex="-1" class="flex-1">
                <!-- Titre de la page (le h1) -->
                @isset($header)
                    <div class="bg-white shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                @if (session('success'))
                    <div class="max-w-7xl mx-auto mt-6 px-4 sm:px-6 lg:px-8">
                        <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800" role="status">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>

            <x-pied-de-page />
        </div>
    </body>
</html>
