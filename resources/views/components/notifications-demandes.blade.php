@php($changements = app(\App\Services\SuiviDemandes::class)->changementsNonVus(auth()->user()))

{{-- F49 : un encadré par changement d'état non encore vu ; « Compris » (formulaire POST, sans JavaScript) l'acquitte. --}}
@foreach ($changements as $etape)
    <div role="status" class="tn-banner tn-banner--info max-w-none flex-wrap items-center">
        <span aria-hidden="true">◆</span>
        <p class="text-sm font-medium">
            @if ($etape->statut === \App\Enums\Statut::Traitee)
                {{ __('Votre demande :reference est maintenant traitée.', ['reference' => $etape->reference]) }}
            @else
                {{ __('Votre demande :reference est maintenant en cours.', ['reference' => $etape->reference]) }}
            @endif
            <a href="{{ route('demandes.show', $etape->demande_id) }}" class="underline">{{ __('Voir la demande :reference', ['reference' => $etape->reference]) }}</a>
        </p>
        <form method="POST" action="{{ route('demandes.etapes.vu', $etape) }}">
            @csrf
            <x-secondary-button type="submit">
                {{ __('Compris') }}<span class="sr-only"> : {{ __('demande :reference', ['reference' => $etape->reference]) }}</span>
            </x-secondary-button>
        </form>
    </div>
@endforeach

{{-- F84 : une réponse de la mairie non encore vue, avec le même bouton « Compris » (POST, sans JavaScript). --}}
@foreach (app(\App\Services\SuiviDemandes::class)->reponsesNonVues(auth()->user()) as $reponse)
    <div role="status" class="tn-banner tn-banner--info max-w-none flex-wrap items-center">
        <span aria-hidden="true">✉</span>
        <p class="text-sm font-medium">
            {{ __('La mairie a répondu à votre demande :reference.', ['reference' => $reponse->reference]) }}
            <a href="{{ route('demandes.show', $reponse->demande_id) }}" class="underline">{{ __('Lire la réponse à la demande :reference', ['reference' => $reponse->reference]) }}</a>
        </p>
        <form method="POST" action="{{ route('demandes.reponses.vu', $reponse) }}">
            @csrf
            <x-secondary-button type="submit">
                {{ __('Compris') }}<span class="sr-only"> : {{ __('réponse à la demande :reference', ['reference' => $reponse->reference]) }}</span>
            </x-secondary-button>
        </form>
    </div>
@endforeach
