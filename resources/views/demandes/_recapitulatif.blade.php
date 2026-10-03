{{-- Contenu du « Récapitulatif de mes demandes » (F56) : partagé par la page du site et le fichier .html autonome. Aucun h1 ici. --}}
<section aria-labelledby="rec-synthese">
    <h2 id="rec-synthese">{{ __('En bref') }}</h2>
    @if ($d['total'] === 0)
        <p>{!! __('dossier.synthese.aucune') !!}</p>
    @else
        <p><strong>{{ $d['phrases']['bilan'] }}</strong></p>
        <ul>
            <li>{{ $d['phrases']['attente'] }}</li>
            <li>{{ $d['phrases']['delai'] }}</li>
            <li>{{ $d['phrases']['activite'] }}</li>
            <li>{{ $d['phrases']['non_lus'] }}</li>
        </ul>
    @endif
</section>

@if ($d['total'] > 0)
    <section aria-labelledby="rec-tableau">
        <h2 id="rec-tableau">{{ __('Toutes mes demandes') }}</h2>
        <div class="tableau-defilant">
            <table>
                <caption>{{ __('Toutes mes demandes, de la plus ancienne à la plus récente') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Référence') }}</th>
                        <th scope="col">{{ __('Objet') }}</th>
                        <th scope="col">{{ __('Service') }}</th>
                        <th scope="col">{{ __('Statut') }}</th>
                        <th scope="col">{{ __('Déposée le') }}</th>
                        <th scope="col">{{ __('Dernière mise à jour') }}</th>
                        <th scope="col">{{ __('Durée écoulée (jours)') }}</th>
                        <th scope="col">{{ __('Ce que cela signifie pour vous') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($d['lignes'] as $ligne)
                        @php($demande = $ligne['demande'])
                        <tr>
                            <th scope="row">{{ $demande->reference }}</th>
                            <td>{{ $demande->objet }}</td>
                            <td>{{ $ligne['service'] }}</td>
                            <td>{{ $demande->statut->label() }}</td>
                            <td>{{ \App\Support\DateLocale::format($demande->created_at) }}</td>
                            <td>{{ \App\Support\DateLocale::format($demande->updated_at) }}</td>
                            <td>{{ $ligne['age_jours'] }}</td>
                            <td>{{ $ligne['signification'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section aria-labelledby="rec-chronologies">
        <h2 id="rec-chronologies">{{ __('Ce qui s\'est passé pour chaque demande') }}</h2>
        @foreach ($d['lignes'] as $ligne)
            <h3>{{ $ligne['demande']->reference }} — {{ $ligne['demande']->objet }}</h3>
            <ul>
                @foreach ($ligne['chronologie'] as $phrase)
                    <li>{{ $phrase }}</li>
                @endforeach
            </ul>
        @endforeach
    </section>
@endif

<p class="pied-de-document">
    {{ __('Document généré le :date.', ['date' => $d['genere_le']]) }}
    {{ __('Ce document ne contient que vos données.') }}
</p>
