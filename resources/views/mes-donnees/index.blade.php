<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Mon espace'), 'url' => route('dashboard')],
            ['label' => __('Mes données')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Mes données') }}</h1>
    </x-slot>

    @php
        $date = fn ($d) => $d ? \App\Support\DateLocale::format($d) : __('Aucune');
    @endphp

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="conserve">
                <h2 id="conserve" class="text-lg font-medium text-gray-900">{{ __('Ce que la ville conserve, et pourquoi') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li><strong>{{ __('Votre compte') }}</strong> : {{ __('votre nom, votre adresse e-mail et votre mot de passe (conservé sous une forme chiffrée, que personne ne peut lire). Ils servent à vous reconnaître quand vous vous connectez.') }}</li>
                    <li><strong>{{ __('Vos demandes') }}</strong> : {{ __('l\'objet et le message de chaque demande. Ils servent à la traiter et à vous répondre.') }}</li>
                    <li><strong>{{ __('Les étapes de suivi') }}</strong> : {{ __('les dates auxquelles votre demande change d\'état. Elles servent à vous informer de l\'avancement.') }}</li>
                    <li><strong>{{ __('Vos préférences d\'affichage') }}</strong> : {{ __('la taille du texte et le thème. Elles servent à retrouver votre affichage à chaque visite.') }}</li>
                </ul>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4" aria-labelledby="informations">
                <h2 id="informations" class="text-lg font-medium text-gray-900">{{ __('Mes informations') }}</h2>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="font-medium text-gray-700">{{ __('Nom') }}</dt><dd>{{ $resume['nom'] }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Adresse e-mail') }}</dt><dd>{{ $resume['email'] }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Rôle') }}</dt><dd>{{ $resume['role'] }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Inscrit le') }}</dt><dd>{{ $resume['inscrit_le'] }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Profil modifié pour la dernière fois le') }}</dt><dd>{{ $resume['profil_mis_a_jour_le'] }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Taille du texte') }}</dt><dd>{{ $resume['taille_texte'] }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Thème') }}</dt><dd>{{ $resume['theme'] }}</dd></div>
                </dl>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <caption class="text-left font-medium text-gray-900 pb-2">{{ __('Mes demandes par statut') }}</caption>
                        <thead class="bg-gray-50 text-left text-gray-700">
                            <tr>
                                <th scope="col" class="px-3 py-2 font-medium">{{ __('Statut') }}</th>
                                <th scope="col" class="px-3 py-2 font-medium">{{ __('Nombre de demandes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach (\App\Enums\Statut::cases() as $statut)
                                <tr>
                                    <th scope="row" class="px-3 py-2 text-left font-normal"><x-statut-badge :statut="$statut" /></th>
                                    <td class="px-3 py-2">{{ $resume['par_statut'][$statut->value] }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <th scope="row" class="px-3 py-2 text-left font-medium">{{ __('Total') }}</th>
                                <td class="px-3 py-2 font-medium">{{ $resume['total'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="font-medium text-gray-700">{{ __('Dernière demande déposée le') }}</dt><dd>{{ $date($resume['derniere_demande']) }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Dernière mise à jour d\'une demande le') }}</dt><dd>{{ $date($resume['derniere_mise_a_jour']) }}</dd></div>
                    <div><dt class="font-medium text-gray-700">{{ __('Dernier changement d\'état le') }}</dt><dd>{{ $date($resume['dernier_changement']) }}</dd></div>
                </dl>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 text-sm text-gray-800" aria-labelledby="actions">
                <h2 id="actions" class="text-lg font-medium text-gray-900">{{ __('Ce que vous pouvez faire') }}</h2>
                <ul class="space-y-4">
                    <li>
                        <a href="{{ route('mes-donnees.export') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Télécharger mes informations (fichier JSON)') }}</a>
                        <p class="text-gray-700">{{ __('Un fichier avec les informations ci-dessus et toutes vos demandes avec leur chronologie.') }}</p>
                    </li>
                    <li>
                        <a href="{{ route('demandes.export-csv') }}" class="underline font-medium text-gray-900 hover:text-gray-600">{{ __('Télécharger le récapitulatif de mes demandes (fichier CSV)') }}</a>
                        <p class="text-gray-700">{{ __('Un tableau à ouvrir dans un tableur.') }}
                            <a href="{{ route('demandes.recapitulatif') }}" class="underline hover:text-gray-900">{{ __('Voir la version imprimable') }}</a></p>
                    </li>
                    <li>
                        <a href="{{ route('mes-donnees.suppression') }}" class="underline font-medium text-red-900 hover:text-red-700">{{ __('Supprimer mon compte') }}</a>
                        <p class="text-gray-700">{{ __('Vous verrez d\'abord ce qui sera supprimé et ce qui sera conservé. Rien n\'est supprimé avant votre confirmation.') }}</p>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
