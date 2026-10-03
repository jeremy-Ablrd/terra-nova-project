@props(['service', 'detail' => false, 'sansRole' => false])

{{-- État d'un service, toujours en texte (jamais la couleur seule) : disponible, interrompu (information) ou désactivé
     (coupure d'urgence). $detail : motif, retour estimé et alternative en plus (fiche, urgences). --}}
@if ($service->estDesactive())
    <div {{ $attributes->merge(['class' => 'service-desactive p-3 text-sm text-gray-900']) }} @if ($detail && ! $sansRole) role="status" @endif>
        <p class="font-semibold">{{ __('Service désactivé') }}</p>
        @if ($detail && $service->motif_interruption)
            <p class="mt-1"><span class="font-medium">{{ __('Motif :') }}</span> {{ $service->motif_interruption }}</p>
        @endif
        @if ($detail && $service->alternative)
            <p class="mt-1"><span class="font-medium">{{ __('À faire à la place :') }}</span> {{ $service->alternative }}</p>
        @endif
    </div>
@elseif ($service->estInterrompu())
    <div {{ $attributes->merge(['class' => 'service-interrompu p-3 text-sm text-gray-900']) }} @if ($detail && ! $sansRole) role="status" @endif>
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
