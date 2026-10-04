<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ \App\Support\Affichage::classes() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <x-tete-assets />
    </head>
    <body class="font-sans text-gray-900 antialiased">
        {{-- Premier élément focusable de la page : saute l'en-tête. --}}
        <a href="#contenu" class="skip-link">{{ __('Aller au contenu') }}</a>

        <x-alertes-banniere />

        <div class="min-h-screen flex flex-col bg-gray-50">
            <header>
                <x-affichage-controles />
            </header>

            <main id="contenu" tabindex="-1" class="flex-1 flex flex-col items-center justify-center px-4 py-8">
                <a href="/" class="flex flex-col items-center gap-2 font-display font-bold text-2xl" aria-label="{{ config('app.name') }} — {{ __('accueil') }}">
                    <x-application-logo class="w-16 aspect-square fill-current text-brand" />
                    <span>Terra Nova</span>
                </a>

                <div class="tn-card w-full sm:max-w-md mt-6 px-6 py-6">
                    {{ $slot }}
                </div>
            </main>

            <x-pied-de-page />
        </div>
    </body>
</html>
