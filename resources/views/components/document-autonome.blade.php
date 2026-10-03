@props(['titre', 'sousTitre' => null])

{{-- Document .html autonome remis à l'habitant : CSS en ligne, aucun script, aucune ressource externe. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} – {{ config('app.name') }}</title>
    <style>{!! \Illuminate\Support\Facades\File::get(resource_path('css/document.css')) !!}
body { margin: 0; padding: 1.5rem; background: #ffffff; }
.document { max-width: 60rem; margin: 0 auto; }</style>
</head>
<body>
<main class="document">
    <h1>{{ $titre }}</h1>
    @if ($sousTitre)
        <p class="sous-titre">{{ $sousTitre }}</p>
    @endif

    {{ $slot }}
</main>
</body>
</html>
