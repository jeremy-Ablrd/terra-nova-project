@props(['priorite' => null])

{{-- Badge de priorité (F80, F86) : texte + glyphe + bordure, jamais la couleur seule. Rien n'est affiché pour une priorité normale. --}}
@if ($priorite !== null && $priorite !== \App\Enums\Priorite::Normale)
    <span {{ $attributes->merge(['class' => 'tn-badge '.$priorite->badgeClasses()]) }}>
        <span aria-hidden="true">{{ $priorite->glyphe() }}</span>{{ $priorite->label() }}
    </span>
@endif
