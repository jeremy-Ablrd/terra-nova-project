@props(['statut'])

{{-- Badge de statut d'une contribution : texte + glyphe + bordure (jamais la couleur seule). --}}
<span {{ $attributes->merge(['class' => 'tn-badge '.$statut->badgeClasses()]) }}>
    <span aria-hidden="true">{{ $statut->glyphe() }}</span>{{ $statut->label() }}
</span>
