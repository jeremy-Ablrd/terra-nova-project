@props(['service'])

{{-- Téléphone d'un service : lien tel: (atteignable au clavier) dont le nom accessible dit « Appeler … au … »
     et contient le numéro affiché (le lecteur d'écran lit le numéro tel que saisi, groupé par paires). --}}
@if ($service->telephone && $service->telephoneHref())
    <a href="{{ $service->telephoneHref() }}"
       aria-label="{{ __('Appeler') }} {{ $service->nom }} {{ __('au') }} {{ $service->telephone }}"
       {{ $attributes->merge(['class' => 'underline font-medium text-gray-900 hover:text-gray-600']) }}>{{ $service->telephone }}</a>
@endif
