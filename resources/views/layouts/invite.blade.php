{{-- En-tête des visiteurs non connectés (pages publiques passant par le layout commun, ex. /alertes). --}}
<header class="bg-white border-b border-gray-100">
    <nav aria-label="{{ __('Navigation principale') }}" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold">
            <x-application-logo class="h-8 w-auto fill-current text-gray-800" />
            Nova Terra
        </a>

        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('services.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Services') }}</a>
            <a href="{{ route('alertes.index') }}" class="px-3 py-2 text-gray-700 hover:text-gray-900">{{ __('Alertes en cours') }}</a>
            <a href="{{ route('login') }}" class="px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100">{{ __('Se connecter') }}</a>
            <a href="{{ route('register') }}" class="px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-700">{{ __('Créer mon compte') }}</a>
        </div>
    </nav>
</header>
