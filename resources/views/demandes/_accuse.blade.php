{{-- Contenu de l'accusé de réception (F83) : partagé par la page du site et le fichier .html autonome. Aucun h1 ici. --}}
<section aria-labelledby="acc-reception">
    <h2 id="acc-reception">{{ __('Votre demande est bien reçue') }}</h2>
    <p>{{ __('La ville de Terra Nova accuse réception de votre demande du :date.', ['date' => $a['recue_le']]) }}</p>
    <dl>
        <dt>{{ __('Numéro de référence') }}</dt><dd><strong>{{ $a['reference'] }}</strong></dd>
        <dt>{{ __('Date et heure de réception') }}</dt><dd>{{ $a['recue_le'] }} ({{ __('heure de La Réunion') }})</dd>
        <dt>{{ __('Objet') }}</dt><dd>{{ $a['objet'] }}</dd>
        <dt>{{ __('Service concerné') }}</dt><dd>{{ $a['service'] }}</dd>
        <dt>{{ __('Statut au moment de la remise') }}</dt><dd>{{ $a['statut'] }} — {{ $a['signification'] }}</dd>
    </dl>
</section>

<section aria-labelledby="acc-suite">
    <h2 id="acc-suite">{{ __('Et maintenant ?') }}</h2>
    <p>{{ __('Conservez cette référence : elle vous permet de suivre votre demande dans « Mes demandes ». Ce document ne remplace pas la réponse de la ville.') }}</p>
</section>

<p class="pied-de-document">
    {{ __('Document remis le :date.', ['date' => $a['delivre_le']]) }}
    {{ __('Il ne contient que les informations de cette demande.') }}
</p>
