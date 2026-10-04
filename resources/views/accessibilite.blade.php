<x-app-layout :title="__('Accessibilité')">
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Accessibilité')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Accessibilité') }}</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-8 text-sm text-gray-900">
                <p>{{ __('Terra Nova doit pouvoir être utilisée par tous les habitants. Voici ce qui est disponible aujourd\'hui sur toutes les pages, pour les visiteurs comme pour les habitants connectés.') }}</p>

                <section aria-labelledby="reglages">
                    <h2 id="reglages" class="text-lg font-semibold">{{ __('Régler l\'affichage') }}</h2>
                    <p class="mt-2">{{ __('La barre « Réglages d\'affichage », en haut de chaque page, propose deux réglages. Elle fonctionne sans JavaScript : chaque bouton recharge la page avec votre choix.') }}</p>
                    <p class="mt-2">{{ __('Votre choix est mémorisé sur cet appareil. Si vous êtes connecté, il est aussi enregistré sur votre compte et vous suit d\'un appareil à l\'autre.') }}</p>
                </section>

                <section aria-labelledby="taille">
                    <h2 id="taille" class="text-lg font-semibold">{{ __('Taille du texte') }}</h2>
                    <ul class="mt-2 list-disc ps-6 space-y-1">
                        <li>{{ __('Trois niveaux : normal, grand (125 %) et très grand (150 %).') }}</li>
                        <li>{{ __('Toutes les tailles de l\'interface sont relatives : elles suivent le niveau choisi et le zoom de votre navigateur.') }}</li>
                        <li>{{ __('Les pages sont conçues pour rester utilisables avec un zoom du navigateur jusqu\'à 200 %, sans texte coupé ni éléments qui se chevauchent. Les tableaux larges défilent dans leur propre cadre.') }}</li>
                    </ul>
                </section>

                <section aria-labelledby="contraste">
                    <h2 id="contraste" class="text-lg font-semibold">{{ __('Contraste et couleurs') }}</h2>
                    <ul class="mt-2 list-disc ps-6 space-y-1">
                        <li>{{ __('Thème standard : le texte atteint au moins 4,5 contre 1 de contraste avec son fond, les bordures de champs et les éléments d\'interface au moins 3 contre 1.') }}</li>
                        <li>{{ __('Thème « Contraste renforcé » : texte noir sur fond blanc, bordures noires, liens soulignés et contour de focus épais.') }}</li>
                        <li>{{ __('Aucune information n\'est donnée par la couleur seule : les statuts des demandes, les niveaux d\'alerte, les services interrompus et les filtres actifs sont écrits en toutes lettres, avec en plus une forme (bordure pleine, double, pointillée ou en tirets, texte souligné ou en gras).') }}</li>
                    </ul>
                </section>

                <section aria-labelledby="clavier">
                    <h2 id="clavier" class="text-lg font-semibold">{{ __('Navigation au clavier') }}</h2>
                    <ul class="mt-2 list-disc ps-6 space-y-1">
                        <li>{{ __('Le premier élément atteint avec la touche Tab est le lien « Aller au contenu », qui saute l\'en-tête et la navigation.') }}</li>
                        <li>{{ __('Chaque page est organisée en repères : en-tête, navigation, contenu principal et pied de page.') }}</li>
                        <li>{{ __('Le focus est toujours visible, et l\'ordre de tabulation suit l\'ordre de lecture.') }}</li>
                        <li>{{ __('Le menu de navigation sur petit écran et le menu du compte s\'ouvrent au clavier et se ferment avec la touche Échap.') }}</li>
                        <li>{{ __('Les boutons, liens de navigation et cases à cocher mesurent au moins 24 pixels de côté à la taille de texte normale.') }}</li>
                        <li>{{ __('Aucune action n\'est réservée à la souris.') }}</li>
                    </ul>
                </section>

                <section aria-labelledby="a-venir">
                    <h2 id="a-venir" class="text-lg font-semibold">{{ __('Ce qui reste à vérifier') }}</h2>
                    <p class="mt-2">{{ __('Les formulaires et les messages d\'erreur doivent encore faire l\'objet d\'un audit complet avec des lecteurs d\'écran.') }}</p>
                </section>

                <section aria-labelledby="signaler">
                    <h2 id="signaler" class="text-lg font-semibold">{{ __('Signaler un problème') }}</h2>
                    <p class="mt-2">{{ __('Si une page reste difficile à utiliser, écrivez-nous depuis « Contacter la mairie » une fois connecté : précisez la page et ce qui vous gêne.') }}</p>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
