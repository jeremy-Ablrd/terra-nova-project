@php($changements = app(\App\Services\SuiviContributions::class)->changementsNonVus(auth()->user()))

{{-- Un encadré par changement d'état non encore vu ; ouvrir la contribution vaut accusé de lecture (aucun script, aucun bouton à actionner). --}}
@foreach ($changements as $etape)
    <div role="status" class="tn-banner tn-banner--info max-w-none flex-wrap items-center">
        <span aria-hidden="true">◆</span>
        <p class="text-sm font-medium">
            @if ($etape->statut === \App\Enums\StatutContribution::PriseEnCompte)
                {{ __('Votre contribution :reference est prise en compte : la ville vous a répondu.', ['reference' => $etape->reference]) }}
            @else
                {{ __('Votre contribution :reference a été examinée par la ville.', ['reference' => $etape->reference]) }}
            @endif
            <a href="{{ route('mes-contributions.show', $etape->contribution_id) }}" class="underline">{{ __('Voir la contribution :reference', ['reference' => $etape->reference]) }}</a>
        </p>
    </div>
@endforeach
