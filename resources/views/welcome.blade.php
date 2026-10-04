<x-app-layout :flash="false">
    {{-- Hero : disponibilité des services, accueil, urgences. Les alertes en cours restent dans le bandeau global au-dessus. --}}
    <section aria-labelledby="titre-accueil" class="bg-gray-100 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 flex flex-wrap items-stretch gap-6">
            <section aria-labelledby="t-dispo" class="tn-card flex-1 basis-[20rem] min-w-0 flex flex-col gap-4 order-2 lg:order-1">
                <h2 id="t-dispo" class="text-lg font-semibold">{{ __('Disponibilité des services') }}</h2>
                @php
                    $part = $nbServices > 0 ? $nbDisponibles / $nbServices : 0;
                @endphp
                <div class="relative self-center w-full max-w-[15rem]">
                    <svg role="img" aria-label="{{ $nbDisponibles }} {{ __('services sur') }} {{ $nbServices }} {{ __('sont disponibles') }}" viewBox="0 0 200 112" class="block w-full h-auto">
                        <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke-width="14" stroke-linecap="round" class="stroke-gray-200"/>
                        <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke-width="14" stroke-linecap="round" stroke-dasharray="{{ round($part * 251.3, 1) }} 251.3" class="stroke-green-200"/>
                    </svg>
                    <p class="absolute inset-x-0 bottom-0 flex flex-col items-center">
                        <span class="font-display font-bold text-4xl leading-tight">{{ $nbDisponibles }}/{{ $nbServices }}</span>
                        <span class="text-sm text-gray-600">{{ __('services disponibles') }}</span>
                    </p>
                </div>
                @forelse ($interrompus as $service)
                    <div>
                        <p class="text-sm font-semibold">{{ $service->nom }}</p>
                        <x-disponibilite-service :service="$service" sans-role />
                    </div>
                @empty
                    <p class="tn-badge tn-badge--success self-start"><span aria-hidden="true">✓</span>{{ __('Tous les services sont disponibles') }}</p>
                @endforelse
                <a href="{{ route('services.index') }}" class="tn-btn tn-btn--secondary mt-auto">{{ __('Voir tous les services') }}</a>
            </section>

            <div class="flex-[2] basis-[24rem] min-w-0 flex flex-col items-center justify-center gap-8 py-6 text-center order-1 lg:order-2">
                <div class="flex flex-col items-center gap-4">
                    <span class="tn-badge tn-badge--info"><span aria-hidden="true">◆</span>{{ __('Services numériques de la ville') }}</span>
                    <h1 id="titre-accueil" class="font-display font-bold text-4xl sm:text-5xl leading-tight text-gray-900 max-w-[14ch]">{{ __('Bienvenue à Terra Nova') }}</h1>
                    <p class="max-w-lg text-lg text-gray-600">{{ __('Les services numériques de la ville, accessibles à chaque habitant depuis son espace personnel.') }}</p>
                    <div class="flex flex-wrap items-center justify-center gap-3 mt-2">
                        @auth
                            <a href="{{ Auth::user()->homeUrl() }}" class="tn-btn tn-btn--primary">{{ __('Accéder à mon espace') }}</a>
                        @else
                            <a href="{{ route('register') }}" class="tn-btn tn-btn--primary">{{ __('Créer mon compte habitant') }}</a>
                            <a href="{{ route('login') }}" class="tn-btn tn-btn--secondary">{{ __('Se connecter') }}</a>
                        @endauth
                    </div>
                </div>

                {{-- Chiffres de la ville : lus en base, jamais codés en dur. --}}
                <div class="w-full max-w-lg">
                    <h2 class="sr-only">{{ __('La ville en chiffres') }}</h2>
                    <dl class="tn-card grid grid-cols-3 text-center py-4 px-0">
                        <div class="px-3 flex flex-col-reverse gap-1">
                            <dt class="text-sm text-gray-600">{{ __('services municipaux') }}</dt>
                            <dd class="font-display font-bold text-4xl">{{ $nbServices }}</dd>
                        </div>
                        <div class="px-3 border-l border-gray-200 flex flex-col-reverse gap-1">
                            <dt class="text-sm text-gray-600">{{ __('alertes en cours') }}</dt>
                            <dd class="font-display font-bold text-4xl">{{ $nbAlertes }}</dd>
                        </div>
                        <div class="px-3 border-l border-gray-200 flex flex-col-reverse gap-1">
                            <dt class="text-sm text-gray-600">{{ __("lieux d'urgence") }}</dt>
                            <dd class="font-display font-bold text-4xl">{{ $nbUrgences }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <section aria-labelledby="t-urgences" class="tn-card flex-1 basis-[20rem] min-w-0 flex flex-col gap-4 order-3">
                <h2 id="t-urgences" class="text-lg font-semibold">{{ __('Urgences') }}</h2>
                @if ($numeroUrgence)
                    <p class="text-sm text-gray-600">{{ __('En cas de détresse vitale, appelez le :numero avant de vous déplacer.', ['numero' => $numeroUrgence]) }}</p>
                    <p class="flex items-baseline gap-3">
                        <span class="font-display font-bold text-5xl leading-tight">{{ $numeroUrgence }}</span>
                        <span class="tn-code">{{ __("numéro d'urgence") }}</span>
                    </p>
                @endif
                <ul class="flex flex-col gap-3 text-sm">
                    @foreach ($urgences as $lieu)
                        <li class="border-t border-gray-200 pt-3">
                            <strong>{{ $lieu->nom }}</strong><br>
                            <span class="text-gray-600">{{ collect([$lieu->quartier, $lieu->horaires])->filter()->implode(' · ') }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('urgences.index') }}" class="tn-btn tn-btn--secondary mt-auto">{{ __('Où aller en urgence') }}</a>
            </section>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex flex-col gap-12">
        <section aria-labelledby="t-services" class="flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="t-services" class="font-display font-semibold text-2xl">{{ __('Services à la une') }}</h2>
                <a href="{{ route('services.index') }}" class="tn-btn tn-btn--secondary">{{ __('Tout le catalogue') }}</a>
            </div>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($alaUne as $service)
                    <li class="tn-card flex flex-col gap-2">
                        <span aria-hidden="true" class="grid place-items-center h-10 w-10 rounded-lg bg-gray-100 font-display font-bold text-lg text-brand-text">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($service->nom, 0, 1)) }}</span>
                        @if ($service->prioritaire)
                            <span class="text-sm font-semibold text-brand-text"><span aria-hidden="true">◆ </span>{{ __('Prioritaire') }}</span>
                        @endif
                        <h3 class="text-lg font-semibold"><a href="{{ route('services.show', $service) }}" class="hover:underline">{{ $service->nom }}</a></h3>
                        <p class="text-sm text-gray-600">{{ $service->resume }}</p>
                        @if ($service->horaires)
                            <p class="text-sm text-gray-900">{{ $service->horaires }}</p>
                        @endif
                        <x-disponibilite-service :service="$service" class="mt-auto" />
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="t-espace" class="tn-card bg-gray-100 flex flex-wrap items-center gap-x-12 gap-y-6 p-8">
            <div class="flex-1 basis-[22rem] flex flex-col gap-3">
                <h2 id="t-espace" class="font-display font-semibold text-2xl">{{ __('Votre espace habitant') }}</h2>
                <p class="text-gray-600">{{ __("Contactez la mairie depuis votre compte : vous recevez un numéro de référence, puis vous suivez l'avancement de chaque demande.") }}</p>
                <p class="flex flex-wrap items-center gap-2">
                    <span class="tn-badge tn-badge--info"><span aria-hidden="true">◆</span>{{ __('Nouvelle') }}</span>
                    <span class="tn-badge tn-badge--warn"><span aria-hidden="true">▲</span>{{ __('En cours') }}</span>
                    <span class="tn-badge tn-badge--success"><span aria-hidden="true">✓</span>{{ __('Traitée') }}</span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ Auth::user()->homeUrl() }}" class="tn-btn tn-btn--primary">{{ __('Accéder à mon espace') }}</a>
                @else
                    <a href="{{ route('register') }}" class="tn-btn tn-btn--primary">{{ __('Créer mon compte habitant') }}</a>
                    <a href="{{ route('login') }}" class="tn-btn tn-btn--secondary">{{ __('Se connecter') }}</a>
                @endauth
            </div>
        </section>
    </div>
</x-app-layout>
