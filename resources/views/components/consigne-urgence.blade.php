@props(['numeros'])

{{-- F86 : la plateforme n'est PAS un service d'urgence en temps réel. Avant l'envoi (/contact) et sur la confirmation. --}}
<div {{ $attributes->merge(['class' => 'tn-banner tn-banner--warn max-w-none flex-col']) }}>
    <p class="font-semibold"><span aria-hidden="true">✚</span> {{ __('Cette plateforme n\'est pas un service d\'urgence en temps réel.') }}</p>
    <p class="text-sm">{{ __('Votre demande est lue par un agent, mais sans délai garanti. En cas d\'urgence médicale, appelez directement les secours :') }}</p>
    @if ($numeros->isNotEmpty())
        <ul class="text-sm space-y-1 list-disc ps-5">
            @foreach ($numeros as $service)
                <li>{{ $service->nom }} : <x-telephone-lien :service="$service" /></li>
            @endforeach
        </ul>
    @endif
    <p class="text-sm font-semibold">{{ __('En cas d\'urgence réelle, appelez le 15 (SAMU) ou le 112.') }}</p>
    <p class="text-sm"><a href="{{ route('urgences.index') }}" class="underline font-medium">{{ __('Voir la page Urgences : adresses et numéros') }}</a></p>
</div>
