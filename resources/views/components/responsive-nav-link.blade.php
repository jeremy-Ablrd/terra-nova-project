@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center min-h-[2.75rem] w-full ps-3 pe-4 py-2 border-l-4 border-brand-text text-start text-base font-bold text-gray-900 bg-white'
            : 'flex items-center min-h-[2.75rem] w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-900 hover:bg-white hover:border-gray-300';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
