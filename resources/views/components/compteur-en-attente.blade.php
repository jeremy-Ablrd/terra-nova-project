@props(['nombre', 'visible' => false])

@php
    // Singulier/pluriel : « 1 demande en attente », « 3 demandes en attente », « Aucune demande en attente ».
    $texte = trans_choice('{0} Aucune demande en attente|{1} :count demande en attente|[2,*] :count demandes en attente', $nombre);
@endphp

@if ($visible)
    <span {{ $attributes }}>{{ $texte }}</span>
@else
    {{-- Pastille de navigation : le chiffre seul est masqué aux lecteurs d'écran, le texte complet leur est lu. --}}
    <span {{ $attributes->merge(['class' => 'ms-2 inline-flex items-center justify-center min-w-[1.5rem] px-1.5 rounded-full border border-gray-400 text-xs font-semibold']) }}>
        <span aria-hidden="true">{{ $nombre }}</span>
        <span class="sr-only">{{ $texte }}</span>
    </span>
@endif
