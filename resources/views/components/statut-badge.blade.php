@props(['statut'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 rounded-full border border-current text-sm leading-6 font-semibold '.$statut->badgeClasses()]) }}>
    {{ $statut->label() }}
</span>
