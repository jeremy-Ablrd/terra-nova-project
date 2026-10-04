<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ \App\Support\Affichage::classes() }}" data-theme="{{ \App\Support\Affichage::dataTheme() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' – ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        <x-tete-assets />
    </head>
    <body class="font-sans antialiased">
        {{-- Premier élément focusable de la page : saute l'en-tête et la navigation. --}}
        <a href="#contenu" class="skip-link">{{ __('Aller au contenu') }}</a>

        @if ($banniere)
            <x-alertes-banniere />
        @endif

        {{-- Connecté : barre latérale unique (menu selon le rôle). Visiteur : en-tête public. --}}
        <div class="min-h-screen flex flex-col lg:flex-row">
            @auth
                @include('layouts.navigation')
            @endauth

            <div class="flex-1 min-w-0 flex flex-col">
                @guest
                    <header>
                        @include('layouts.invite')
                    </header>
                @endguest

                <!-- Fil d'Ariane (optionnel) -->
                @isset($breadcrumb)
                    {{ $breadcrumb }}
                @endisset

                <main id="contenu" tabindex="-1" class="flex-1">
                    <!-- Titre de la page (le h1) -->
                    @isset($header)
                        <div class="max-w-[90rem] mx-auto pt-8 pb-2 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    @endisset

                    @if ($flash && session('success'))
                        <div class="max-w-[90rem] mx-auto mt-6 px-4 sm:px-6 lg:px-8">
                            <div class="rounded-lg bg-green-50 border-2 border-green-200 p-4 text-sm font-semibold text-green-800" role="status">
                                {{ session('success') }}
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </main>

                <x-pied-de-page />
            </div>
        </div>
    </body>
</html>
