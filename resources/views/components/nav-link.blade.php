@props(['active'])

{{-- Lien de la barre latérale : page courante = fond marqué, barre à gauche et gras, en plus d'aria-current. --}}
@php
$classes = ($active ?? false)
    ? 'nav-lateral flex items-center justify-between gap-2 min-h-[2.75rem] px-3 rounded-lg border-l-4 border-brand-text bg-gray-100 font-bold text-gray-900'
    : 'nav-lateral flex items-center justify-between gap-2 min-h-[2.75rem] px-3 rounded-lg border-l-4 border-transparent font-medium text-gray-900 hover:bg-gray-100';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
