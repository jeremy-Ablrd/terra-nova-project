<x-app-layout :flash="false" :banniere="false">
    @php
        $part = $nbServices > 0 ? $nbDisponibles / $nbServices : 0;
        $plusHaut = $alertes->first()?->niveau;
    @endphp

    <!-- Hero : photo de la ville en pleine largeur, texte centré et encarts autour -->
    <section aria-labelledby="titre-accueil" class="relative w-full bg-gray-100 border-b border-gray-200 overflow-hidden">
        {{-- Image décorative (alt vide) sous un voile de la couleur du fond : le texte garde son contraste. --}}
        <img src="{{ asset('images/hero-terra-nova.webp') }}" alt="" width="1280" height="720" decoding="async" loading="eager" fetchpriority="low"
             class="absolute inset-0 h-full w-full object-cover pointer-events-none">
        <div aria-hidden="true" class="absolute inset-0 bg-gray-100 opacity-[0.88] pointer-events-none"></div>

        <div class="relative max-w-[90rem] mx-auto box-border px-6 pt-8 pb-12 flex flex-wrap items-stretch gap-6">

            <div class="flex-[1_1_20rem] min-w-0 flex flex-col gap-6">

                <section id="meteo" class="tn-card flex flex-col gap-4" aria-labelledby="t-meteo">
                    <h2 id="t-meteo" class="m-0 font-sans text-[1.125rem] leading-6 font-semibold">{{ __('Météo actuelle à Terra Nova') }}</h2>
                    {{-- Aucune source météo n'est branchée sur l'application : les valeurs restent « non disponible ». --}}
                    <div class="grid grid-cols-2 border-t border-gray-200">
                        <div class="py-3 flex flex-col gap-1">
                            <span class="flex items-baseline gap-1"><span class="font-display font-bold text-[2.25rem] leading-10">—</span><span class="tn-code">°C</span></span>
                            <span class="text-sm leading-5 text-gray-600">{{ __('Température') }}</span>
                        </div>
                        <div class="py-3 pl-4 border-l border-gray-200 flex flex-col gap-1">
                            <span class="flex items-baseline gap-1"><span class="font-display font-bold text-[2.25rem] leading-10">—</span><span class="tn-code">km/h</span></span>
                            <span class="text-sm leading-5 text-gray-600">{{ __('Vent') }}</span>
                        </div>
                        <div class="py-3 border-t border-gray-200 flex flex-col gap-1">
                            <span class="flex items-baseline gap-1"><span class="font-display font-bold text-[2.25rem] leading-10">—</span><span class="tn-code">%</span></span>
                            <span class="text-sm leading-5 text-gray-600">{{ __('Humidité') }}</span>
                        </div>
                        <div class="py-3 pl-4 border-t border-l border-gray-200 flex flex-col gap-1">
                            <span class="flex items-baseline gap-1"><span class="font-display font-bold text-[2.25rem] leading-10">—</span><span class="tn-code">°C</span></span>
                            <span class="text-sm leading-5 text-gray-600">{{ __('Ressenti') }}</span>
                        </div>
                    </div>
                    <p class="m-0 text-sm leading-5 text-gray-600">{{ __('Mise à jour : non disponible') }}</p>
                </section>

                <section id="alertes" class="tn-card flex flex-col gap-4" aria-labelledby="t-alertes">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="t-alertes" class="m-0 font-sans text-[1.125rem] leading-6 font-semibold">{{ __('Alertes en cours') }}</h2>
                        @if ($plusHaut)
                            <span class="tn-badge {{ $plusHaut->badge() }}"><span aria-hidden="true">{{ $plusHaut->glyphe() }}</span>{{ trans_choice(':count active|:count actives', $nbAlertes) }}</span>
                        @endif
                    </div>
                    @forelse ($alertes->take(3) as $alerte)
                        <div role="{{ $alerte->niveau->role() }}" aria-labelledby="alerte-{{ $alerte->id }}"
                             class="tn-banner {{ str_replace('tn-badge--', 'tn-banner--', $alerte->niveau->badge()) }} max-w-none flex-col gap-1">
                            <span class="tn-badge {{ $alerte->niveau->badge() }} bg-transparent"><span aria-hidden="true">{{ $alerte->niveau->glyphe() }}</span>{{ $alerte->niveau->label() }}</span>
                            <strong id="alerte-{{ $alerte->id }}" class="text-base">{{ $alerte->titre }}</strong>
                            <span class="text-sm leading-5">{{ collect([$alerte->secteur, __('Depuis le').' '.\App\Support\DateLocale::format($alerte->starts_at)])->filter()->implode(' · ') }}</span>
                            <a href="{{ route('alertes.show', $alerte) }}" class="text-sm font-semibold underline">{{ __('Voir le détail') }}<span class="sr-only"> {{ __("de l'alerte") }} {{ $alerte->titre }}</span></a>
                        </div>
                    @empty
                        <p class="m-0 text-sm leading-5 text-gray-600">{{ __('Aucune alerte en cours.') }}</p>
                    @endforelse
                    <a href="{{ route('alertes.index') }}" class="tn-btn tn-btn--secondary justify-center">{{ __('Toutes les alertes en cours') }}</a>
                </section>

            </div>

            <div class="flex-[2_1_28rem] min-w-0 relative flex flex-col items-center justify-center gap-8 px-4 py-12 text-center">
                <div class="relative flex flex-col items-center gap-4">
                    <span class="tn-badge tn-badge--info"><span aria-hidden="true">◆</span>{{ __('Services numériques de la ville') }}</span>
                    <h1 id="titre-accueil" class="m-0 max-w-[14ch] font-display font-bold text-[3rem] leading-[3.25rem] tracking-[-0.01em]">{{ __('Bienvenue à Terra Nova') }}</h1>
                    <p class="m-0 max-w-[32rem] text-[1.125rem] leading-7 text-gray-600">{{ __('Les services numériques de la ville, accessibles à chaque habitant depuis son espace personnel.') }}</p>
                    <div class="flex flex-wrap justify-center items-center gap-x-4 gap-y-3 mt-2">
                        @auth
                            <a href="{{ Auth::user()->homeUrl() }}" class="tn-btn tn-btn--primary">{{ __('Accéder à mon espace') }}</a>
                        @else
                            <a href="{{ route('register') }}" class="tn-btn tn-btn--primary">{{ __('Créer mon compte habitant') }}</a>
                            <a href="{{ route('login') }}" class="tn-btn tn-btn--secondary">{{ __('Se connecter') }}</a>
                        @endauth
                    </div>
                </div>
                <div class="tn-card relative w-full max-w-[34rem] box-border py-4 px-0 flex flex-wrap">
                    <div class="flex-[1_1_8rem] px-4 flex flex-col items-center gap-1">
                        <span class="font-display font-bold text-[2.25rem] leading-10">{{ $nbServices }}</span>
                        <span class="text-sm leading-5 text-gray-600">{{ __('services municipaux') }}</span>
                    </div>
                    <div class="flex-[1_1_8rem] px-4 border-l border-gray-200 flex flex-col items-center gap-1">
                        <span class="font-display font-bold text-[2.25rem] leading-10">{{ $nbAlertes }}</span>
                        <span class="text-sm leading-5 text-gray-600">{{ __('alertes en cours') }}</span>
                    </div>
                    <div class="flex-[1_1_8rem] px-4 border-l border-gray-200 flex flex-col items-center gap-1">
                        <span class="font-display font-bold text-[2.25rem] leading-10">{{ $nbUrgences }}</span>
                        <span class="text-sm leading-5 text-gray-600">{{ __("lieux d'urgence") }}</span>
                    </div>
                </div>
            </div>

            <div class="flex-[1_1_20rem] min-w-0 flex flex-col gap-6">

                <section class="tn-card flex flex-col gap-4" aria-labelledby="t-dispo">
                    <h2 id="t-dispo" class="m-0 font-sans text-[1.125rem] leading-6 font-semibold">{{ __('Disponibilité des services') }}</h2>
                    <div class="relative self-center w-full max-w-[15rem]">
                        <svg role="img" aria-label="{{ $nbDisponibles }} {{ __('services sur') }} {{ $nbServices }} {{ __('sont disponibles') }}" viewBox="0 0 200 112" class="block w-full h-auto">
                            <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke-width="14" stroke-linecap="round" class="stroke-gray-200"/>
                            <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke-width="14" stroke-linecap="round" stroke-dasharray="{{ round($part * 251.3, 1) }} 251.3" class="stroke-green-200"/>
                        </svg>
                        <div class="absolute inset-x-0 bottom-0 flex flex-col items-center">
                            <span class="font-display font-bold text-[2.25rem] leading-10">{{ $nbDisponibles }}/{{ $nbServices }}</span>
                            <span class="text-sm leading-5 text-gray-600">{{ __('services disponibles') }}</span>
                        </div>
                    </div>
                    @foreach ($interrompus as $service)
                        <div class="tn-banner tn-banner--warn max-w-none text-sm leading-5">
                            <span aria-hidden="true" class="font-bold">▲</span>
                            <div><strong>{{ $service->nom }} : {{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::after($service->disponibilite->label(), 'Service ')) }}</strong>{{ $service->motif_interruption }}</div>
                        </div>
                    @endforeach
                </section>

                <section id="urgences" class="tn-card flex flex-col gap-4" aria-labelledby="t-urgences">
                    <h2 id="t-urgences" class="m-0 font-sans text-[1.125rem] leading-6 font-semibold">{{ __('Urgences') }}</h2>
                    @if ($numeroUrgence)
                        <p class="m-0 text-sm leading-5 text-gray-600">{{ __('En cas de détresse vitale, appelez le :numero avant de vous déplacer.', ['numero' => $numeroUrgence]) }}</p>
                        <div class="flex items-baseline gap-3">
                            <span class="font-display font-bold text-[3rem] leading-[3.25rem]">{{ $numeroUrgence }}</span>
                            <span class="tn-code">{{ __("numéro d'urgence") }}</span>
                        </div>
                    @endif
                    <ul class="list-none m-0 p-0 flex flex-col gap-3 text-sm leading-5">
                        @foreach ($urgences as $lieu)
                            <li class="border-t border-gray-200 pt-3"><strong>{{ $lieu->nom }}</strong><br><span class="text-gray-600">{{ collect([$lieu->quartier, $lieu->horaires])->filter()->implode(' · ') }}</span></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('urgences.index') }}" class="tn-btn tn-btn--secondary justify-center">{{ __('Où aller en urgence') }}</a>
                </section>

            </div>

        </div>
    </section>

    <div class="w-full max-w-[90rem] mx-auto box-border px-6 py-12 flex flex-col gap-12">

        <!-- Services à la une : section distincte du hero -->
        <section id="services" aria-labelledby="t-services" class="flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="t-services" class="m-0 font-display font-semibold text-2xl leading-8">{{ __('Services à la une') }}</h2>
                <a href="{{ route('services.index') }}" class="tn-btn tn-btn--secondary">{{ __('Tout le catalogue') }}</a>
            </div>
            <div class="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-6">
                @foreach ($alaUne as $service)
                    <a href="{{ route('services.show', $service) }}" class="tn-card tn-service group max-w-none">
                        <span class="tn-service__icon" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($service->nom, 0, 1)) }}</span>
                        @if ($service->prioritaire)
                            <span class="text-sm font-semibold text-brand-text"><span aria-hidden="true">◆ </span>{{ __('Prioritaire') }}</span>
                        @else
                            <span class="text-sm leading-5">&nbsp;</span>
                        @endif
                        <h3 class="group-hover:underline">{{ $service->nom }}</h3>
                        <p>{{ $service->resume }}</p>
                        <p>{{ $service->horaires }}</p>
                        @if ($service->estDesactive())
                            <span class="tn-badge tn-badge--danger justify-self-start"><span aria-hidden="true">⊘</span>{{ __('Désactivé') }}</span>
                        @elseif ($service->estInterrompu())
                            <span class="tn-badge tn-badge--warn justify-self-start"><span aria-hidden="true">▲</span>{{ __('Interrompu') }}</span>
                        @else
                            <span class="tn-badge tn-badge--success justify-self-start"><span aria-hidden="true">✓</span>{{ __('Disponible') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>

        <!-- Espace habitant -->
        <section aria-labelledby="t-espace" class="tn-card flex flex-wrap items-center gap-x-12 gap-y-6 p-8 bg-gray-100">
            <div class="flex-[1_1_22rem] flex flex-col gap-3">
                <h2 id="t-espace" class="m-0 font-display font-semibold text-2xl leading-8">{{ __('Votre espace habitant') }}</h2>
                <p class="m-0 text-gray-600">{{ __("Contactez la mairie depuis votre compte : vous recevez un numéro de référence, puis vous suivez l'avancement de chaque demande.") }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tn-badge tn-badge--info"><span aria-hidden="true">◆</span>{{ __('Nouvelle') }}</span>
                    <span class="tn-badge tn-badge--warn"><span aria-hidden="true">▲</span>{{ __('En cours') }}</span>
                    <span class="tn-badge tn-badge--success"><span aria-hidden="true">✓</span>{{ __('Traitée') }}</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
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
