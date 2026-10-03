@props(['statut'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium '.$statut->badgeClasses()]) }}>
    {{ $statut->label() }}
</span>
