@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'tn-banner tn-banner--success max-w-none text-sm font-semibold']) }}>
        {{ $status }}
    </div>
@endif
