@props(['contribution'])

{{-- Frise d'une contribution : l'étape actuelle est indiquée en texte (et aria-current), pas par la couleur seule. La ville est l'acteur, jamais un nom. --}}
<ol class="space-y-3">
    @foreach ($contribution->etapes as $etape)
        @php($actuelle = $loop->last)
        <li @if ($actuelle) aria-current="step" @endif
            class="border-s-4 ps-3 {{ $actuelle ? 'border-gray-900 font-semibold' : 'border-gray-300' }}">
            <x-statut-contribution :statut="$etape->statut" />
            @if ($actuelle)
                <span class="ms-1 text-sm text-gray-900">{{ __('Étape actuelle') }}</span>
            @endif
            <span class="block text-sm text-gray-700">
                <time datetime="{{ $etape->created_at->toIso8601String() }}">{{ \App\Support\DateLocale::format($etape->created_at) }}</time>
                — {{ $etape->statut->phrase() }}
            </span>
        </li>
    @endforeach
</ol>
