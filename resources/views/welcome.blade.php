<x-app-layout :flash="false">
    <section aria-labelledby="titre-accueil" class="bg-gray-100 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 flex flex-wrap items-center gap-x-12 gap-y-10">
            <div class="flex-1 basis-[22rem] min-w-0 flex flex-col items-start gap-4">
                <span class="tn-badge tn-badge--info"><span aria-hidden="true">◆</span>{{ __('Services numériques de la ville') }}</span>
                <h1 id="titre-accueil" class="font-display font-bold text-4xl sm:text-5xl leading-tight text-gray-900">{{ __('Bienvenue à Terra Nova') }}</h1>
                <p class="max-w-xl text-lg text-gray-600">{{ __('Les services numériques de la ville, accessibles à chaque habitant depuis son espace personnel.') }}</p>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    @auth
                        <a href="{{ Auth::user()->homeUrl() }}" class="tn-btn tn-btn--primary">{{ __('Accéder à mon espace') }}</a>
                    @else
                        <a href="{{ route('register') }}" class="tn-btn tn-btn--primary">{{ __('Créer mon compte habitant') }}</a>
                        <a href="{{ route('login') }}" class="tn-btn tn-btn--secondary">{{ __('Se connecter') }}</a>
                    @endauth
                </div>
            </div>

            {{-- Chiffres de la ville : lus en base, jamais codés en dur. --}}
            <div class="flex-1 basis-[20rem] min-w-0">
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
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 flex flex-col gap-12">
        <div class="grid gap-6 md:grid-cols-2">
            <section aria-labelledby="t-dispo" class="tn-card flex flex-col gap-4">
                <h2 id="t-dispo" class="text-lg font-semibold">{{ __('Disponibilité des services') }}</h2>
                <p class="flex items-baseline gap-2">
                    <span class="font-display font-bold text-4xl">{{ $nbDisponibles }}/{{ $nbServices }}</span>
                    <span class="text-sm text-gray-600">{{ __('services disponibles') }}</span>
                </p>
                @forelse ($interrompus as $service)
                    <div>
                        <p class="text-sm font-semibold">{{ $service->nom }}</p>
                        <x-disponibilite-service :service="$service" sans-role />
                    </div>
                @empty
                    <p class="tn-badge tn-badge--success self-start"><span aria-hidden="true">✓</span>{{ __('Tous les services sont disponibles') }}</p>
                @endforelse
                <a href="{{ route('services.index') }}" class="tn-btn tn-btn--secondary self-start">{{ __('Voir tous les services') }}</a>
            </section>

            <section aria-labelledby="t-urgences" class="tn-card flex flex-col gap-4">
                <h2 id="t-urgences" class="text-lg font-semibold">{{ __('Urgences') }}</h2>
                <ul class="flex flex-col gap-3 text-sm">
                    @foreach ($urgences as $lieu)
                        <li class="border-t border-gray-200 pt-3">
                            <strong>{{ $lieu->nom }}</strong><br>
                            <span class="text-gray-600">{{ collect([$lieu->quartier, $lieu->horaires])->filter()->implode(' · ') }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('urgences.index') }}" class="tn-btn tn-btn--secondary self-start">{{ __('Où aller en urgence') }}</a>
            </section>
        </div>

        <section aria-labelledby="t-services" class="flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="t-services" class="font-display font-semibold text-2xl">{{ __('Services à la une') }}</h2>
                <a href="{{ route('services.index') }}" class="tn-btn tn-btn--secondary">{{ __('Tout le catalogue') }}</a>
            </div>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($alaUne as $service)
                    <li class="tn-card flex flex-col gap-2">
                        @if ($service->prioritaire)
                            <span class="text-sm font-semibold text-brand-text"><span aria-hidden="true">◆ </span>{{ __('Prioritaire') }}</span>
                        @endif
                        <h3 class="text-lg font-semibold"><a href="{{ route('services.show', $service) }}" class="hover:underline">{{ $service->nom }}</a></h3>
                        <p class="text-sm text-gray-600">{{ $service->resume }}</p>
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
