<x-app-layout>
    <x-slot name="breadcrumb">
        <x-breadcrumb :items="[
            ['label' => __('Accueil'), 'url' => url('/')],
            ['label' => __('Sécurité')],
        ]" />
    </x-slot>

    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">{{ __('Sécurité de votre compte') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-3 text-sm text-gray-800" aria-labelledby="protege">
                <h2 id="protege" class="text-lg font-medium text-gray-900">{{ __('Ce qui protège votre compte') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li><strong>{{ __('Mot de passe d\'au moins 10 caractères') }}</strong> : {{ __('exigé quand vous créez votre compte ou changez de mot de passe. Il est conservé sous une forme chiffrée, que personne ne peut lire, pas même la ville.') }}</li>
                    <li><strong>{{ __('Limitation des essais de connexion') }}</strong> : {{ __('après plusieurs mots de passe faux, la connexion est bloquée quelques minutes. Le message est le même que l\'adresse e-mail existe ou non, pour ne rien révéler sur les comptes.') }}</li>
                    <li><strong>{{ __('Alerte de nouvelle connexion') }}</strong> : {{ __('quand votre compte est utilisé depuis un appareil inconnu, un encadré apparaît sur votre espace. Vous pouvez répondre « C\'était moi » ou « Ce n\'était pas moi » : dans ce cas nous déconnectons les autres appareils et vous invitons à changer votre mot de passe.') }}</li>
                    <li><strong>{{ __('Vos appareils') }}</strong> : {{ __('la page « Mes connexions » liste les appareils qui ont utilisé votre compte. Vous pouvez en oublier un ou vous déconnecter partout.') }}</li>
                    <li><strong>{{ __('Espaces séparés') }}</strong> : {{ __('chaque rôle (habitant, agent, administrateur) n\'accède qu\'à ses propres pages. Un accès refusé à une page réservée est enregistré.') }}</li>
                    <li><strong>{{ __('Protections du navigateur') }}</strong> : {{ __('le site refuse d\'être affiché dans le cadre d\'un autre site, interdit le chargement de scripts venant d\'ailleurs et n\'appelle aucun service extérieur.') }}</li>
                    <li><strong>{{ __('Journal de sécurité sans données personnelles') }}</strong> : {{ __('seules les adresses e-mail masquées (par exemple c***@e***.fr) y figurent, jamais un mot de passe ni le contenu d\'une demande. Il est effacé au bout de 30 jours.') }}</li>
                </ul>
            </section>

            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-3 text-sm text-gray-800" aria-labelledby="ne-fait-pas">
                <h2 id="ne-fait-pas" class="text-lg font-medium text-gray-900">{{ __('Ce que la plateforme ne fait pas') }}</h2>
                <ul class="list-disc ps-5 space-y-1">
                    <li>{{ __('Pas de double authentification : votre mot de passe est le seul secret. Choisissez-en un long et unique.') }}</li>
                    <li>{{ __('Pas d\'e-mail d\'alerte : l\'alerte de nouvelle connexion s\'affiche sur le site, à votre prochaine visite.') }}</li>
                    <li>{{ __('Pas de vérification de votre mot de passe auprès d\'un service extérieur : nous ne savons pas s\'il a déjà fuité ailleurs.') }}</li>
                    <li>{{ __('Une limite technique : la politique de sécurité du contenu garde l\'autorisation « unsafe-eval » : les scripts de la plateforme peuvent encore évaluer du code, ce qu\'exige la bibliothèque de ses menus (Alpine). Les scripts en ligne et ceux d\'un autre site restent interdits.') }}</li>
                    <li>{{ __('Aucune protection ne remplace la vigilance : ne communiquez jamais votre mot de passe.') }}</li>
                </ul>
            </section>

            <section class="bg-white border border-gray-200 rounded-xl p-6 space-y-2 text-sm text-gray-800" aria-labelledby="que-faire">
                <h2 id="que-faire" class="text-lg font-medium text-gray-900">{{ __('Que faire en cas de doute ?') }}</h2>
                <p>{{ __('Connectez-vous, ouvrez « Mes connexions », oubliez les appareils inconnus, déconnectez-vous partout, puis changez votre mot de passe.') }}</p>
                @auth
                    <p><a href="{{ route('mes-connexions.index') }}" class="underline font-medium text-gray-900">{{ __('Mes connexions') }}</a></p>
                @else
                    <p><a href="{{ route('login') }}" class="underline font-medium text-gray-900">{{ __('Se connecter') }}</a></p>
                @endauth
            </section>
        </div>
    </div>
</x-app-layout>
