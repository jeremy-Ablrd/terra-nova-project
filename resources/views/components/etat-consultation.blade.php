@props(['projet', 'detail' => false])

{{-- État de la consultation d'un projet, calculé à chaque requête : toujours en texte (+ glyphe), jamais la couleur seule. --}}
@php($etat = $projet->etatConsultation())
<span {{ $attributes }}>
    <span class="tn-badge {{ $etat->badgeClasses() }}"><span aria-hidden="true">{{ $etat->glyphe() }}</span>{{ $etat->label() }}</span>
    @if ($detail || $etat !== \App\Enums\EtatConsultation::Aucune)
        <span class="block mt-1 text-sm text-gray-700">
            @if ($etat === \App\Enums\EtatConsultation::AVenir)
                {{ __('Elle s\'ouvrira le :date.', ['date' => \App\Support\DateLocale::format($projet->consultation_debut_at)]) }}
            @elseif ($etat === \App\Enums\EtatConsultation::Ouverte && $projet->consultation_fin_at)
                {{ __('Vous pouvez donner votre avis jusqu\'au :date.', ['date' => \App\Support\DateLocale::format($projet->consultation_fin_at)]) }}
            @elseif ($etat === \App\Enums\EtatConsultation::Ouverte)
                {{ __('Vous pouvez donner votre avis dès maintenant.') }}
            @elseif ($etat === \App\Enums\EtatConsultation::Close)
                {{ __('Elle s\'est terminée le :date.', ['date' => \App\Support\DateLocale::format($projet->consultation_fin_at ?? $projet->consultation_debut_at)]) }}
            @endif
        </span>
    @endif
</span>
