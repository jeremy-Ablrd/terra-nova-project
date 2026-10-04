@props(['demande', 'montrerAgent' => false])

{{-- D11/F26 : chronologie d'une demande. L'étape actuelle est indiquée en texte (et aria-current), pas par la couleur seule.
     F84 : les réponses des agents s'y insèrent à leur date (« Réponse de la mairie »), sans changer l'étape actuelle. --}}
@php
    $reponses = $demande->relationLoaded('reponses') ? $demande->reponses : collect();
    $derniere = $demande->etapes->last();
    $elements = $demande->etapes->map(fn ($e) => ['etape' => true, 'o' => $e])
        ->concat($reponses->map(fn ($r) => ['etape' => false, 'o' => $r]))
        ->sortBy(fn ($i) => $i['o']->created_at->getTimestamp() * 10 + ($i['etape'] ? 0 : 1))
        ->values();
@endphp
<ol class="space-y-3">
    @foreach ($elements as $element)
        @if ($element['etape'])
            @php($etape = $element['o'])
            @php($actuelle = $etape->is($derniere))
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
        @else
            @php($reponse = $element['o'])
            <li class="border-s-4 ps-3 border-gray-300">
                <span class="tn-badge tn-badge--info"><span aria-hidden="true">✉</span>{{ __('Réponse de la mairie') }}</span>
                <span class="block text-sm text-gray-700">
                    <time datetime="{{ $reponse->created_at->toIso8601String() }}">{{ \App\Support\DateLocale::format($reponse->created_at) }}</time>
                    @if ($montrerAgent)
                        — {{ __('par :nom', ['nom' => $reponse->agent_nom ?? __('un agent')]) }}
                        <span class="block text-gray-900 whitespace-pre-line">{{ $reponse->texte }}</span>
                    @endif
                </span>
            </li>
        @endif
    @endforeach
</ol>
