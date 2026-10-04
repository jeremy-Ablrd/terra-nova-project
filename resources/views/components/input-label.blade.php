@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-gray-900']) }}>
    {{ $value ?? $slot }}
</label>
