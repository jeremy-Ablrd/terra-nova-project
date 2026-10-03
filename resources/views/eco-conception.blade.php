<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Éco-conception')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Éco-conception de la plateforme') }}</h1>
    </x-slot>

    @php
        // Mise en forme des valeurs lues dans resources/data/mesures-poids.json (aucun chiffre écrit à la main ici).
        $ko = fn (?int $octets) => $octets === null ? '—' : number_format($octets / 1024, 1, ',', ' ').' '.__('Ko');
        $sec = fn (?int $ms) => $ms === null ? '—' : number_format($ms / 1000, 1, ',', ' ').' s';
        $dansBudget = fn (?array $p) => $p === null ? '—'
            : (($p['transfere_octets'] <= $budget['octets'] && $p['requetes'] <= $budget['requetes']) ? __('Oui') : __('Non'));
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="pourquoi">
                <h2 id="pourquoi" class="text-lg font-medium text-gray-900">{{ __('Pourquoi des pages légères ?') }}</h2>
                <p>{{ __('Une page légère se charge plus vite quand la connexion est lente ou limitée, consomme moins de données mobiles et sollicite moins de serveurs et de réseau. Les informations et les actions essentielles restent disponibles : seul ce qui est superflu a été retiré.') }}</p>
                <p>{{ __('Budget que se fixe la plateforme pour une page courante, chargée à froid :') }}
                    <strong>{{ $ko($budget['octets']) }}</strong> {{ __('transférés au plus') }},
                    <strong>{{ trans_choice('{1} :count requête|[2,*] :count requêtes', $budget['requetes']) }}</strong> {{ __('au plus') }}.</p>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4" aria-labelledby="mesures">
                <h2 id="mesures" class="text-lg font-medium text-gray-900">{{ __('Mesures avant et après') }}</h2>

                @if ($avant === null && $apres === null)
                    <p class="text-sm text-gray-700">{{ __('Aucune mesure n\'est encore disponible.') }}</p>
                @else
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        @foreach (['avant' => [__('Mesure « avant »'), $avant], 'apres' => [__('Mesure « après »'), $apres], 'sans' => [__('Mesure « après », sans règles serveur'), $sansRegles]] as [$titre, $mesure])
                            @if ($mesure)
                                <div>
                                    <dt class="font-medium text-gray-900">{{ $titre }}</dt>
                                    <dd class="text-gray-800">
                                        {{ __('Mesurée le :date', ['date' => \App\Support\DateLocale::format($mesure['date'])]) }}<br>
                                        {{ __('Navigateur : :nom', ['nom' => $mesure['environnement']['navigateur']]) }}<br>
                                        {{ __('Réseau : :profil (:debit kbit/s, latence :latence ms)', ['profil' => $mesure['environnement']['reseau']['profil'], 'debit' => $mesure['environnement']['reseau']['debit_descendant_kbit_s'], 'latence' => $mesure['environnement']['reseau']['latence_ms']]) }}<br>
                                        {{ __('Serveur : :regles', ['regles' => $mesure['environnement']['regles_serveur']]) }}<br>
                                        {{ __('Application : :appli', ['appli' => $mesure['environnement']['application']]) }}<br>
                                        {{ __('Chargements par page : :n (temps : médiane), cache du navigateur : :cache', ['n' => $mesure['environnement']['chargements_par_page'], 'cache' => $mesure['environnement']['cache_navigateur']]) }}
                                    </dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('Poids transféré, nombre de requêtes et temps de chargement de chaque page, avant et après les optimisations') }}</caption>
                            <thead class="bg-gray-50 text-left text-gray-700">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Page') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Visiteur') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Transféré avant') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Transféré après') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Transféré après, sans règles serveur') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Requêtes avant') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Requêtes après') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Chargement avant') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Chargement après') }}</th>
                                    <th scope="col" class="px-3 py-2 font-medium">{{ __('Dans le budget (après)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($lignes as $ligne)
                                    <tr>
                                        <th scope="row" class="px-3 py-2 text-left font-medium whitespace-nowrap">{{ $ligne['chemin'] }}</th>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $ligne['role'] }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $ko($ligne['avant']['transfere_octets'] ?? null) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $ko($ligne['apres']['transfere_octets'] ?? null) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $ko($ligne['sans_regles']['transfere_octets'] ?? null) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $ligne['avant']['requetes'] ?? '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $ligne['apres']['requetes'] ?? '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $sec($ligne['avant']['chargement_ms'] ?? null) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $sec($ligne['apres']['chargement_ms'] ?? null) }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $dansBudget($ligne['apres']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @foreach (['avant' => [__('avant'), $avant], 'apres' => [__('après'), $apres]] as [$nom, $mesure])
                        @if ($mesure)
                            @php($tiers = collect($mesure['pages'])->pluck('domaines_tiers')->flatten()->unique()->values())
                            <p class="text-sm text-gray-800">
                                @if ($tiers->isEmpty())
                                    {{ __('Domaines tiers contactés (:moment) : aucun.', ['moment' => $nom]) }}
                                @else
                                    {{ __('Domaines tiers contactés (:moment) :', ['moment' => $nom]) }} {{ $tiers->implode(', ') }}.
                                @endif
                            </p>
                        @endif
                    @endforeach
                @endif
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="choix">
                <h2 id="choix" class="text-lg font-medium text-gray-900">{{ __('Choix de conception appliqués') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li>{{ __('Aucune requête vers un site tiers : les polices sont celles du système de l\'utilisateur, rien n\'est téléchargé.') }}</li>
                    <li>{{ __('Peu de JavaScript : un seul petit script, chargé en différé. Les menus et les formulaires restent utilisables sans lui.') }}</li>
                    <li>{{ __('Feuille de style purgée : seules les règles utilisées sont livrées.') }}</li>
                    <li>{{ __('Pas d\'image décorative, pas de vidéo, pas de lecture automatique. Les icônes sont des dessins vectoriels intégrés. Toute image future devra avoir un texte alternatif, des dimensions et un chargement différé.') }}</li>
                    <li>{{ __('Fichiers de la plateforme conservés longtemps dans le navigateur et compressés, quand l\'hébergeur le permet.') }}</li>
                    <li>{{ __('Mode économie de données : si votre navigateur le demande (en-tête Save-Data), le script n\'est pas chargé du tout.') }}</li>
                    <li>{{ __('Respect de la préférence « réduire les animations ».') }}</li>
                    <li>{{ __('Pages connectées marquées « privées » : jamais conservées dans un cache partagé.') }}</li>
                </ul>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="limites">
                <h2 id="limites" class="text-lg font-medium text-gray-900">{{ __('Limites, en toute honnêteté') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li>{{ __('Ces chiffres sont des mesures de laboratoire (un navigateur, un réseau simulé, une base de démonstration) : ils indiquent un ordre de grandeur, pas l\'expérience de chaque habitant.') }}</li>
                    <li>{{ __('La compression et le cache long ont été simulés par un petit serveur qui reproduit les règles du fichier de configuration. Le gain réel dépend de l\'hébergeur.') }}</li>
                    <li>{{ __('Le poids transféré ne dit rien de l\'énergie consommée côté serveur ou côté appareil : nous ne mesurons pas l\'empreinte carbone, seulement le poids, les requêtes et le temps.') }}</li>
                    <li>{{ __('Quelques fonctions annexes, comme la confirmation de suppression de compte, ont encore besoin du script.') }}</li>
                    <li>{{ __('Une page qui dépasserait le budget est signalée dans le tableau plutôt que cachée.') }}</li>
                </ul>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3 text-sm text-gray-800" aria-labelledby="hebergeur">
                <h2 id="hebergeur" class="text-lg font-medium text-gray-900">{{ __('Ce qui dépend de l\'hébergeur') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li>{{ __('La compression des réponses (module de compression du serveur web).') }}</li>
                    <li>{{ __('Les en-têtes de cache (module d\'en-têtes) et la prise en compte du fichier de configuration public/.htaccess.') }}</li>
                    <li>{{ __('Le type de stockage des sessions et du cache : un stockage en base de données ajoute des requêtes SQL à chaque visite.') }}</li>
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
