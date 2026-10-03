@props(['demande', 'montrerAgent' => false])

{{-- D11/F26 : chronologie d'une demande. L'étape actuelle est indiquée en texte (et aria-current), pas par la couleur seule. --}}
<ol class="space-y-3">
    @foreach ($demande->etapes as $etape)
        @php($actuelle = $loop->last)
        <li @if ($actuelle) aria-current="step" @endif
            class="border-s-4 ps-3 {{ $actuelle ? 'border-gray-900 font-semibold' : 'border-gray-300' }}">
            <x-statut-badge :statut="$etape->statut" />
            @if ($actuelle)
                <span class="ms-1 text-sm text-gray-900">{{ __('Étape actuelle') }}</span>
            @endif
            <span class="block text-sm text-gray-700">
                <time datetime="{{ $etape->created_at->toIso8601String() }}">{{ \App\Support\DateLocale::format($etape->created_at) }}</time>
                @if ($etape->statut !== \App\Enums\Statut::Nouvelle)
                    —
                    @if ($montrerAgent && $etape->agent_nom)
                        {{ __('par :nom', ['nom' => $etape->agent_nom]) }}
                    @elseif (! $montrerAgent)
                        {{ __('Un agent') }}
                    @endif
                @endif
            </span>
        </li>
    @endforeach
</ol>
