<x-guest-layout>
    <h1 class="text-xl font-semibold text-gray-900">Accès refusé</h1>
    <p class="mt-2 text-sm text-gray-600">
        Votre profil ne vous permet pas d'accéder à cette page. Si vous pensez qu'il s'agit d'une erreur, contactez un administrateur de Nova Terra.
    </p>
    <p class="mt-6">
        @auth
            <a href="{{ Auth::user()->homeUrl() }}" class="underline text-sm text-gray-700 hover:text-gray-900">Retour à mon espace</a>
        @else
            <a href="{{ url('/') }}" class="underline text-sm text-gray-700 hover:text-gray-900">Retour à l'accueil</a>
        @endauth
    </p>
</x-guest-layout>
