@props(['items'])

{{-- Fil d'Ariane : $items = [['label' => '…', 'url' => '…'], …] ; le dernier élément (sans url) est la page courante. --}}
<nav aria-label="{{ __('Fil d\'Ariane') }}" class="bg-gray-50">
    <ol class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 pt-4 flex flex-wrap items-center gap-x-2 text-sm text-gray-700">
        @foreach ($items as $item)
            <li class="flex items-center gap-x-2">
                @if (! $loop->first)
                    <span aria-hidden="true">/</span>
                @endif
                @if (isset($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" class="underline hover:text-gray-900">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page" class="font-medium text-gray-900">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
