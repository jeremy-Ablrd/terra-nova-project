@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-[2.75rem] rounded-lg border-2 border-gray-300 bg-white text-gray-900 focus:border-accent focus:ring-accent']) }}>
