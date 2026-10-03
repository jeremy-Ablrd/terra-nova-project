@props(['nombre'])

{{-- F49 : pastille de navigation « changements non lus » ; le chiffre seul est masqué aux lecteurs d'écran, le texte complet leur est lu. --}}
@if ($nombre > 0)
    <span class="ms-2 inline-flex items-center justify-center min-w-[1.5rem] px-1.5 rounded-full border border-gray-400 text-xs font-semibold">
        <span aria-hidden="true">{{ $nombre }}</span>
        <span class="sr-only">{{ trans_choice('{1} :count changement non lu|[2,*] :count changements non lus', $nombre) }}</span>
    </span>
@endif
