<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">
                Mon espace {{ Auth::user()->role === \App\Enums\Role::Citoyen ? 'citoyen' : Auth::user()->role->label() }}
            </h1>
            @if (Auth::user()->isCitoyen())
                <x-primary-link href="{{ route('contact.create') }}">{{ __('Nouvelle demande') }}</x-primary-link>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <x-alertes-connexion />
            <x-notifications-demandes />
            @if (Auth::user()->isCitoyen())
                <x-notifications-contributions />
            @endif

            <div class="tn-card overflow-hidden p-0">
                <div class="p-6 text-gray-900">
                    <p class="text-lg font-medium">Bienvenue, {{ Auth::user()->name }} !</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Vous êtes connecté à votre espace personnel de Terra Nova en tant que
                        <span class="tn-badge">{{ Auth::user()->role->label() }}</span>.
                    </p>
                </div>
            </div>

            {{-- L'historique des demandes est réservé au citoyen (/mes-demandes renvoie 403 aux agents et aux admins). --}}
            @if (Auth::user()->isCitoyen())
                @php
                    $demandes = Auth::user()->demandes()->latest()->get();
                    $parStatut = $demandes->countBy(fn ($d) => $d->statut->value);
                @endphp
                <div class="tn-card overflow-hidden p-0">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Mes demandes</h2>
                            <a href="{{ route('demandes.index') }}" class="text-sm underline text-gray-600 hover:text-gray-900">Voir tout</a>
                        </div>

                        @if ($demandes->isEmpty())
                            <p class="mt-4 text-sm text-gray-600">Vous n'avez encore aucune demande.</p>
                        @else
                            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                                @foreach (\App\Enums\Statut::cases() as $statut)
                                    <span class="inline-flex items-center gap-2 text-gray-700">
                                        <x-statut-badge :statut="$statut" /> {{ $parStatut[$statut->value] ?? 0 }}
                                    </span>
                                @endforeach
                            </div>
                            <ul class="mt-4 divide-y divide-gray-100 text-sm">
                                @foreach ($demandes->take(3) as $demande)
                                    <li class="py-2 flex items-center justify-between gap-4">
                                        <a href="{{ route('demandes.show', $demande) }}" class="underline text-gray-900 hover:text-gray-600"><span class="tn-code">{{ $demande->reference }}</span> — {{ $demande->objet }}</a>
                                        <x-statut-badge :statut="$demande->statut" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="tn-card overflow-hidden p-0">
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Participer') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Donnez votre avis sur les projets de la ville (un avis n\'est pas un vote), proposez une idée, et suivez ce que la ville fait de vos contributions.') }}</p>
                        <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <a href="{{ route('projets.index') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Projets de la ville') }}</a>
                            <a href="{{ route('idees.create') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Proposer une idée') }}</a>
                            <a href="{{ route('mes-contributions.index') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Mes contributions') }}</a>
                        </p>
                    </div>
                </div>

                <div class="tn-card overflow-hidden p-0">
                    <div class="p-6">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('Mes données') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Consultez ce que la ville conserve sur vous, téléchargez vos informations et le récapitulatif de vos demandes, ou supprimez votre compte.') }}</p>
                        <p class="mt-3 text-sm"><a href="{{ route('mes-donnees.index') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Mes données') }}</a></p>
                    </div>
                </div>
            @endif

            <div class="tn-card overflow-hidden p-0">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Mes informations</h2>
                        <a href="{{ route('profile.edit') }}" class="text-sm underline text-gray-600 hover:text-gray-900">Modifier</a>
                    </div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-3 text-sm">
                        <div>
                            <dt class="text-gray-500">Nom</dt>
                            <dd class="mt-1 text-gray-900">{{ Auth::user()->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Adresse e-mail</dt>
                            <dd class="mt-1 text-gray-900">{{ Auth::user()->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Habitant depuis le</dt>
                            <dd class="mt-1 text-gray-900">{{ \App\Support\DateLocale::format(Auth::user()->created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
