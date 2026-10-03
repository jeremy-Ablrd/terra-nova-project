@props(['service', 'detail' => false])

{{-- Disponibilité d'un service, toujours en texte (jamais la couleur seule). $detail : motif et alternative en plus (fiche). --}}
@if ($service->estInterrompu())
    <div {{ $attributes->merge(['class' => 'service-interrompu p-3 text-sm text-gray-900']) }} @if ($detail) role="status" @endif>
        <p class="font-semibold">{{ __('Service interrompu') }}</p>
        @if ($detail && $service->motif_interruption)
            <p class="mt-1"><span class="font-medium">{{ __('Motif :') }}</span> {{ $service->motif_interruption }}</p>
        @endif
        <p class="mt-1">
            <span class="font-medium">{{ __('Retour estimé :') }}</span>
            {{ $service->retour_estime_at ? \App\Support\DateLocale::format($service->retour_estime_at) : __('non communiqué') }}
        </p>
        @if ($detail && $service->alternative)
            <p class="mt-1"><span class="font-medium">{{ __('À faire en attendant :') }}</span> {{ $service->alternative }}</p>
        @endif
    </div>
@else
    <p {{ $attributes->merge(['class' => 'text-sm text-gray-900']) }}><span class="font-medium">{{ __('Disponible') }}</span></p>
@endif
