<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                Mon espace {{ Auth::user()->role === \App\Enums\Role::Citoyen ? 'citoyen' : Auth::user()->role->label() }}
            </h1>
            @if (Auth::user()->isCitoyen())
                <x-primary-link href="{{ route('contact.create') }}">{{ __('Nouvelle demande') }}</x-primary-link>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-lg font-medium">Bienvenue, {{ Auth::user()->name }} !</p>
                    <p class="mt-1 text-sm text-gray-600">
                        Vous êtes connecté à votre espace personnel de Nova Terra en tant que
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ Auth::user()->role->label() }}</span>.
                    </p>
                </div>
            </div>

            @php
                $demandes = Auth::user()->demandes()->latest()->get();
                $parStatut = $demandes->countBy(fn ($d) => $d->statut->value);
            @endphp
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="font-medium text-gray-900">Mes demandes</h3>
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
                                    <a href="{{ route('demandes.show', $demande) }}" class="underline text-gray-900 hover:text-gray-600">{{ $demande->reference }} — {{ $demande->objet }}</a>
                                    <x-statut-badge :statut="$demande->statut" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <h3 class="font-medium text-gray-900">Mes informations</h3>
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
