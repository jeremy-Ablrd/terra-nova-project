@props(['service', 'court' => false, 'avecLienDemande' => true])

@php
    $citoyen = auth()->user()?->isCitoyen() === true;
    $invite = auth()->guest();
    $autresServices = route('services.index', ['categorie' => $service->categorie->value]);
@endphp

{{-- « Prochaine action possible » : la phrase dit quoi faire selon l'état du service ; le lien n'est donné qu'à qui peut s'en servir
     (habitant : la demande ; visiteur : la connexion ; agent et administrateur n'ont pas de formulaire de demande). --}}
<div {{ $attributes->merge(['class' => 'text-sm text-gray-900 space-y-1']) }}>
    <p class="font-medium">{{ __('Prochaine action possible :') }}</p>

    @if ($service->estDesactive())
        <p>{{ __('Pas de demande possible pour ce service pour le moment.') }}</p>
        @if ($court && $service->alternative)
            <p><span class="font-medium">{{ __('À faire à la place :') }}</span> {{ $service->alternative }}</p>
        @endif
        <p class="flex flex-wrap gap-x-4 gap-y-1">
            @if ($citoyen)
                <a href="{{ route('contact.create') }}" class="underline font-medium">{{ __('Contacter la mairie sans choisir de service') }}</a>
            @elseif ($invite)
                <a href="{{ route('login') }}" class="underline font-medium">{{ __('Se connecter pour contacter la mairie') }}</a>
            @endif
            <a href="{{ $autresServices }}" class="underline">{{ __('Voir les autres services de la catégorie « :categorie »', ['categorie' => $service->categorie->label()]) }}</a>
        </p>
    @elseif ($service->estInterrompu())
        <p>{{ __('Vous pouvez quand même faire une demande : elle sera traitée dès le retour du service.') }}</p>
        @if ($court && $service->alternative)
            <p><span class="font-medium">{{ __('À faire en attendant :') }}</span> {{ $service->alternative }}</p>
        @endif
        <p class="flex flex-wrap gap-x-4 gap-y-1">
            @if ($avecLienDemande && $citoyen)
                <a href="{{ route('contact.create', ['service_id' => $service->id]) }}" class="underline font-medium">{{ __('Faire une demande à ce service') }}</a>
            @elseif ($avecLienDemande && $invite)
                <a href="{{ route('login') }}" class="underline font-medium">{{ __('Se connecter pour faire une demande') }}</a>
            @endif
            <a href="{{ $autresServices }}" class="underline">{{ __('Voir les autres services de la catégorie « :categorie »', ['categorie' => $service->categorie->label()]) }}</a>
        </p>
    @else
        <p>
            @if ($avecLienDemande && $citoyen)
                <a href="{{ route('contact.create', ['service_id' => $service->id]) }}" class="underline font-medium">{{ __('Faire une demande à ce service') }}</a>
            @elseif ($avecLienDemande && $invite)
                <a href="{{ route('login') }}" class="underline font-medium">{{ __('Se connecter pour faire une demande') }}</a>
            @else
                {{ __('Ce service est disponible : consultez sa fiche pour les horaires et le contact.') }}
            @endif
        </p>
    @endif
</div>
