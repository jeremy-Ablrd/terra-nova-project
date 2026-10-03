<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes connexions')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Mes connexions') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('securite'))
                <p role="status" class="rounded border-2 border-gray-800 bg-white p-3 text-sm font-medium text-gray-900">{{ session('securite') }}</p>
            @endif

            <x-alertes-connexion />

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4" aria-labelledby="appareils">
                <h2 id="appareils" class="text-lg font-medium text-gray-900">{{ __('Les appareils qui ont utilisé mon compte') }}</h2>
                <p class="text-sm text-gray-800">{{ __('Un « appareil » est un navigateur sur un ordinateur ou un téléphone. Si vous ne reconnaissez pas l\'un d\'eux, oubliez-le, déconnectez-vous partout et changez votre mot de passe.') }}</p>

                @if ($appareils->isEmpty())
                    <p class="text-sm text-gray-700">{{ __('Aucun appareil enregistré pour le moment.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Appareils qui ont utilisé mon compte, du plus récent au plus ancien') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-700">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Appareil') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Première connexion') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Dernière connexion') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($appareils as $appareil)
                                    <tr>
                                        <th scope="row" class="px-3 py-2 text-left font-medium">
                                            {{ $appareil->libelle }}
                                            @if ($courant !== null && hash_equals($appareil->jeton_hash, $courant))
                                                <span class="block text-xs font-semibold border border-gray-700 rounded px-1 w-fit">{{ __('Cet appareil') }}</span>
                                            @endif
                                        </th>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ \App\Support\DateLocale::format($appareil->premiere_vue_at) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ \App\Support\DateLocale::format($appareil->derniere_vue_at) }}</td>
                                        <td class="px-3 py-2">
                                            <form method="POST" action="{{ route('mes-connexions.oublier', $appareil) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-secondary-button type="submit">{{ __('Oublier cet appareil') }}<span class="sr-only"> : {{ $appareil->libelle }}</span></x-secondary-button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3" aria-labelledby="partout">
                <h2 id="partout" class="text-lg font-medium text-gray-900">{{ __('Me déconnecter partout') }}</h2>
                <p class="text-sm text-gray-800">{{ __('Tous vos autres appareils seront déconnectés. Cet appareil reste connecté.') }}</p>
                <form method="POST" action="{{ route('mes-connexions.deconnexion') }}">
                    @csrf
                    <x-primary-button type="submit">{{ __('Me déconnecter partout') }}</x-primary-button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
