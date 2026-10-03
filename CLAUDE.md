# Contexte : concours 24h by WebCup 2026

Je suis seul, 24h de développement. Application web pour la ville fictive de Nova Terra (la doc de l'API écrit parfois "Terra Nova" : utiliser "Nova Terra" dans l'interface, comme dans les demandes).
Les demandes viennent d'une API officielle. Un premier lot est disponible dès le lancement, d'autres arrivent par vagues. On est jugé sur des fonctionnalités réellement utilisables et intégrées à l'application.

## Stack
- Laravel 13, PHP 8.4, développement en local, déploiement ensuite sur un serveur HODI
- Interface en français

## API des demandes
- GET https://24h.webcup.fr/wp-json/webcup/v1/requests
- Auth : header X-Webcup-Api-Key. La clé est dans .env (WEBCUP_API_KEY, ou API_KEY en repli, lue via config/services.php), jamais dans le code ni dans ce fichier. Appel côté serveur uniquement.
- Réponse : { api_version, session{...}, requests[...] }. Champs utiles d'une demande : request_code (clé stable), message_public, difficulty_level, xp_total, group_name, sort_order, visible_since_wave (vague d'apparition, 0 = dès le lancement ; remplace wave_number/is_initial).
- Interroger toutes les 30 s (commande artisan planifiée). Stocker en base avec upsert sur request_code. L'interface lit la base, jamais l'API directement.
- arrival_time est un délai depuis le début du concours (02:00:00 = H+2), pas une heure du jour.
- Ne jamais coder en dur le nombre de demandes ni les vagues.

## Règles d'architecture
- Le rôle n'est JAMAIS lu depuis la requête d'inscription : fixé à "citoyen" côté serveur. Les comptes agent et admin viennent d'un seeder.
- Permissions appliquées côté serveur (middleware ou Gates), pas seulement en masquant des liens. Un citoyen sur /agent doit recevoir un 403.
- Séparation stricte : /agent/* réservé au rôle agent, /admin/* au rôle admin. L'admin n'accède ni aux outils agents ni aux demandes des habitants. « Contacter la mairie » (/contact, /contact/confirmation/{demande}, lien de nav, boutons « Nouvelle demande ») est réservé au rôle citoyen (middleware `role:citoyen`) : agent et admin reçoivent un 403.
- Statuts des demandes : enum PHP fixe (nouvelle, en_cours, traitee), avec badge visuel et filtre dans la vue agent.
- Après l'envoi du formulaire de contact : message de confirmation visible avec un numéro de référence.
- Les demandes des habitants (D04, F22) utilisent le modèle Demande (table demandes, enum Statut, composant statut-badge, DemandePolicy). Ne pas créer de second modèle équivalent. Les demandes de l'API de type exactement « Citoyen » sont importées dans ce même modèle (user_id null, `request_code` unique, `demandeur_nom`) par `ImporteDemandesApi` : jamais d'insert/upsert en masse (save() pour l'événement `created`), jamais d'écrasement d'une demande déjà importée (le statut local prime), et ne jamais supposer qu'une demande a un `user` (utiliser `nom_demandeur`, comparaison stricte sur user_id). Ne pas remettre WithoutModelEvents dans les seeders : la référence est générée par un événement du modèle.
- Chaque nouvelle fonctionnalité doit être reliée à la navigation et à l'existant (pas de page orpheline).
- Code simple et lisible, pas de sur-ingénierie : le temps est limité.
- Après chaque fonctionnalité : lancer les tests ou vérifier la route à la main, puis me résumer en 3 lignes max ce qui a changé.

## Ne pas faire
- Ne pas mettre de secret dans un fichier versionné.
- Ne pas installer de dépendance lourde sans me demander.
- Ne pas démarrer une fonctionnalité sans que je te la demande explicitement.
- Quand un prompt dit d'attendre mon accord, s'arrêter et poser la question.

## Règles transversales
- Langues : tout texte visible (vues, composants, messages de validation et flash) passe par __(), le français étant la langue de base. Aucun texte en dur dans les nouvelles vues. Le sélecteur de langue viendra plus tard.
- Accessibilité : html lang dynamique, un seul h1 par page, landmarks (header, nav, main, footer) avec un lien « Aller au contenu » en premier, chaque champ avec un label associé et ses erreurs liées (aria-describedby), focus visible, aucune information portée par la couleur seule (un badge de statut a toujours un texte), alt sur les images, libellés explicites sur les boutons et liens.
- Thème : couleurs via variables CSS, texte en rem (pas de px fixes pour le texte), pour que « contraste élevé » et « grande taille de texte » soient de simples bascules plus tard.
- Dates : fuseau de l'application `Indian/Reunion` (UTC+4, sans heure d'été) via `APP_TIMEZONE` (config/app.php, .env, .env.example, phpunit.xml). Toute date affichée dans une vue passe par `App\Support\DateLocale::format()` (jj/mm/aaaa HH:mm, heure locale) : aucune date brute ni `translatedFormat` dans les vues. Ne jamais changer APP_TIMEZONE une fois des données écrites (les colonnes datetime sont stockées en heure locale).
- Navigation : chaque page passe par le layout commun, qui prévoit un emplacement pour un fil d'Ariane.

## État du projet
- **Laravel** : 13.34.0 (`laravel/framework ^13.17`).
- **PHP** : 8.4.26 en local (composer.json exige `^8.3`).
- **Starter kit / auth** : Laravel Breeze 2.4 installé (stack Blade + Tailwind/Alpine). Présents : contrôleurs `app/Http/Controllers/Auth/*`, `ProfileController`, `routes/auth.php` (login, register, reset password, vérification email), vues `resources/views/auth/*`, layouts `app`/`guest`, route `/espace` (nom `dashboard`, auth + verified). Tests Breeze dans `tests/Feature/Auth`.
- **Rôles** : enum `App\Enums\Role` (citoyen, agent, admin), colonne `users.role` (défaut `citoyen`, hors `Fillable`). Inscription → citoyen forcé côté serveur. Seeder : `citoyen@`, `agent@`, `admin@novaterra.test` (mots de passe dans `.env` : `SEED_CITOYEN_PASSWORD`, `SEED_AGENT_PASSWORD`, `SEED_ADMIN_PASSWORD` ; aléatoire affiché au seed si vide). Helpers `isCitoyen/isAgent/isAdmin/homeUrl` sur User ; connexion → /espace, /agent ou /admin selon le rôle ; admin : /admin/comptes (changement de rôle). Middleware `role:...` (alias, `EnsureUserHasRole`) : /agent → agent seul, /admin/* → admin seul (403 personnalisé `errors/403`, invité → login) ; l’action POST de changement de rôle revérifie isAdmin. DemandePolicy : `view` (propriétaire ou agent), `updateStatus` (agent seul).
- **Tests** : `pdo_sqlite` n'est pas activé dans le php.ini local → lancer `php -d extension=pdo_sqlite vendor/bin/phpunit`.
- **Base de données** : MySQL configuré dans `.env` (`DB_DATABASE=webcup`, 127.0.0.1:3306) ; `.env.example` et le défaut de `config/database.php` restent sur SQLite (`database/database.sqlite` existe). Migrations de base exécutées (users, cache, jobs).
- **API WebCup** : ingestion faite, sans vue : service `App\Services\NovaTerraApi`, commande `php artisan novaterra:sync`, table/modèle `ApiRequest` (`api_requests`, distinct de `Demande`), planifiée toutes les 30 s (`php artisan schedule:work`, inactive sans clé). Import (étape A) : `novaterra:import-demandes`, et en fin de `novaterra:sync` / du bouton admin (même `NovaTerraApi::sync()`, dans le verrou, même si l'appel réseau échoue) : crée une `Demande` par ligne `api_requests.requester_type = 'Citoyen'` (13 au 03/10/2026). Verrou `Cache::lock('novaterra.sync', 120)` autour de toute synchro (commande, planificateur, bouton) : exige un store de cache partagé entre processus (`CACHE_STORE=database`, `redis` ou `file`, jamais `array` en prod). Cache : `novaterra.session`, `novaterra.last_sync_at`, `novaterra.last_error`. Page admin `/admin/synchronisation` (état + bouton « Actualiser maintenant », même `NovaTerraApi::sync()` que la commande). Pas encore de vue agent des demandes de l'API.
- **Espace agent** : Centre technique municipal = liste des demandes agent (/agent/demandes) ; `/agent` (accueil) et `/agent/demandes` (F22 étape 1 : liste paginée de toutes les demandes, `DemandePolicy::viewAny` agent seul, fil d'Ariane via `x-breadcrumb` et slot `breadcrumb` du layout). Étape 2 faite : filtre `?statut=` (liens GET, valeur inconnue ignorée) + compteurs par statut (1 requête groupée). D17 : « en attente » = statut `nouvelle` (`Demande::enAttente()`), compteur dans la nav agent (view composer, requête seulement pour un agent) et en tête de `/agent`. Changement de statut et étapes : étape 3 à venir.
- **Langue** : `APP_LOCALE=fr`, traductions dans `lang/fr.json` (chaînes `__()` des vues Breeze) et `lang/fr/*.php` (validation, auth, passwords).
