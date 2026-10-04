@props(['active'])

<a {{ $attributes->merge(['class' => 'nav-lien text-sm']) }} @if ($active ?? false) aria-current="page" @endif>
    {{ $slot }}
</a>
