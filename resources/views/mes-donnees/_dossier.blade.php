{{-- Contenu de « Mon dossier » (F55) : partagé par la page du site et le fichier .html autonome. Aucun h1 ici. --}}
<section aria-labelledby="doc-qui">
    <h2 id="doc-qui">{{ __('Qui vous êtes pour la ville') }}</h2>
    <dl>
        <dt>{{ __('Nom') }}</dt><dd>{{ $d['compte']['nom'] }}</dd>
        <dt>{{ __('Adresse e-mail') }}</dt><dd>{{ $d['compte']['email'] }}</dd>
        <dt>{{ __('Inscrit le') }}</dt><dd>{{ $d['compte']['inscrit_le'] }} ({{ trans_choice('{0} aujourd\'hui|{1} il y a :count jour|[2,*] il y a :count jours', $d['compte']['anciennete_jours']) }})</dd>
    </dl>
    <p>{{ $d['compte']['role'] }}</p>
</section>

<section aria-labelledby="doc-conserve">
    <h2 id="doc-conserve">{{ __('Ce que la ville conserve et pourquoi') }}</h2>
    <p>{{ __('Voici chaque donnée que la ville conserve sur vous, la raison, la durée, et où la modifier ou la supprimer.') }}</p>
    <div class="tableau-defilant">
        <table>
            <caption>{{ __('Données conservées par la ville') }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('Donnée') }}</th>
                    <th scope="col">{{ __('Pourquoi (finalité)') }}</th>
                    <th scope="col">{{ __('Durée de conservation') }}</th>
                    <th scope="col">{{ __('Où la modifier ou la supprimer') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($d['conservation'] as $ligne)
                    <tr>
                        <th scope="row">{{ __($ligne['donnee']) }}</th>
                        <td>{{ __($ligne['finalite']) }}</td>
                        <td>{{ __($ligne['duree']) }}</td>
                        <td>
                            @if ($ligne['action'])
                                <a href="{{ route($ligne['action']['route']) }}">{{ __($ligne['action']['libelle']) }}</a>
                            @else
                                {{ __('Non modifiable : géré automatiquement par la plateforme.') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section aria-labelledby="doc-activite">
    <h2 id="doc-activite">{{ __('Votre activité') }}</h2>
    <p>{!! $d['total'] === 0 ? __('dossier.synthese.aucune') : e($d['phrases']['bilan']) !!}</p>
    @if ($d['total'] > 0)
        <div class="tableau-defilant">
            <table>
                <caption>{{ __('Vos demandes par statut') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Statut') }}</th>
                        <th scope="col">{{ __('Nombre de demandes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (\App\Enums\Statut::cases() as $statut)
                        <tr><th scope="row">{{ $statut->label() }}</th><td>{{ $d['par_statut'][$statut->value] }}</td></tr>
                    @endforeach
                    <tr><th scope="row">{{ __('Total') }}</th><td>{{ $d['total'] }}</td></tr>
                </tbody>
            </table>
        </div>
        <ul>
            <li>{{ __('Première demande déposée le :date.', ['date' => $d['premiere_demande']]) }}</li>
            <li>{{ __('Dernière demande déposée le :date.', ['date' => $d['derniere_demande']]) }}</li>
            <li>{{ $d['phrases']['delai'] }}</li>
            <li>{{ $d['phrases']['attente'] }}</li>
            <li>{{ $d['phrases']['activite'] }}</li>
            <li>{{ $d['phrases']['non_lus'] }}</li>
        </ul>
    @else
        <p>{{ $d['phrases']['non_lus'] }}</p>
    @endif
</section>

<section aria-labelledby="doc-reponses">
    <h2 id="doc-reponses">{{ __('Les réponses de la mairie') }}</h2>
    @if ($d['reponses']->isEmpty())
        <p>{{ __('Vous n\'avez reçu aucune réponse directe de la mairie.') }}</p>
    @else
        <p>{{ trans_choice('{1} Vous avez reçu :count réponse directe de la mairie.|[2,*] Vous avez reçu :count réponses directes de la mairie.', $d['reponses']->count()) }}</p>
        @foreach ($d['reponses'] as $reponse)
            <h3>{{ __('Demande :reference, le :date', ['reference' => $reponse['reference'], 'date' => $reponse['date']]) }}</h3>
            <p>{{ $reponse['texte'] }}</p>
        @endforeach
    @endif
</section>

<section aria-labelledby="doc-contributions">
    <h2 id="doc-contributions">{{ __('Vos contributions') }}</h2>
    <p>{{ $d['phrases_contributions']['bilan'] }}</p>
    @if ($d['contributions']->isNotEmpty())
        <div class="tableau-defilant">
            <table>
                <caption>{{ __('Vos contributions, de la plus ancienne à la plus récente') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Référence') }}</th>
                        <th scope="col">{{ __('Type') }}</th>
                        <th scope="col">{{ __('Objet') }}</th>
                        <th scope="col">{{ __('Envoyée le') }}</th>
                        <th scope="col">{{ __('Suivi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($d['contributions'] as $c)
                        <tr>
                            <th scope="row">{{ $c['reference'] }}</th>
                            <td>{{ $c['type'] }}</td>
                            <td>{{ $c['intitule'] }}</td>
                            <td>{{ $c['date'] }}</td>
                            <td>{{ $c['statut'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @foreach ($d['contributions'] as $c)
            <h3>{{ $c['reference'] }}</h3>
            <p>{{ $c['message'] }}</p>
            @if ($c['reponse'])
                <p><strong>{{ __('Réponse de la ville :') }}</strong> {{ $c['reponse'] }}</p>
            @endif
        @endforeach
    @endif
</section>

<section aria-labelledby="doc-preferences">
    <h2 id="doc-preferences">{{ __('Vos préférences') }}</h2>
    <dl>
        <dt>{{ __('Taille du texte') }}</dt><dd>{{ $d['preferences']['taille_texte'] }}</dd>
        <dt>{{ __('Thème') }}</dt><dd>{{ $d['preferences']['theme'] }}</dd>
    </dl>
</section>

<section aria-labelledby="doc-actions">
    <h2 id="doc-actions">{{ __('Ce que vous pouvez faire') }}</h2>
    <ul>
        <li><a href="{{ route('profile.edit') }}">{{ __('Modifier mon profil') }}</a></li>
        <li><a href="{{ route('demandes.recapitulatif') }}">{{ __('Lire et imprimer le récapitulatif de mes demandes') }}</a> — <a href="{{ route('demandes.recapitulatif.telecharger') }}">{{ __('télécharger la version imprimable') }}</a></li>
        <li><a href="{{ route('mes-donnees.suppression') }}">{{ __('Supprimer mon compte') }}</a></li>
    </ul>
    <p>{{ __('Si vous supprimez votre compte, vos demandes ne sont pas supprimées : elles sont anonymisées. Le texte de chaque demande est remplacé, et seuls restent son numéro de référence, son service, son statut, ses dates et ses étapes, sans votre nom.') }}</p>
    <p>{{ __('Vos contributions (avis, idées, commentaires) sont anonymisées de la même façon : leur texte est effacé, et seuls restent leur référence, leur statut, la réponse de la ville et leurs dates.') }}</p>
</section>

<p class="pied-de-document">
    {{ __('Document généré le :date.', ['date' => $d['genere_le']]) }}
    {{ __('Ce document ne contient que vos données.') }}
</p>
