{{-- En-tête des visiteurs non connectés (pages publiques passant par le layout commun). --}}
<div class="bg-white border-b border-gray-200">
    <nav aria-label="{{ __('Navigation principale') }}" class="max-w-[90rem] mx-auto px-6 py-3 min-h-[4.5rem] flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
        <a href="{{ url('/') }}" class="flex items-center gap-2 font-display font-bold text-xl leading-7">
            <x-application-logo class="h-8 w-8" />
            Terra Nova
        </a>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <a href="{{ route('services.index') }}" class="nav-lien" @if (request()->routeIs('services.*')) aria-current="page" @endif>{{ __('Services') }}</a>
            <a href="{{ route('urgences.index') }}" class="nav-lien" @if (request()->routeIs('urgences.*')) aria-current="page" @endif>{{ __('Urgences') }}</a>
            <a href="{{ route('projets.index') }}" class="nav-lien" @if (request()->routeIs('projets.*')) aria-current="page" @endif>{{ __('Participer') }}</a>
            <a href="{{ route('alertes.index') }}" class="nav-lien" @if (request()->routeIs('alertes.*')) aria-current="page" @endif>{{ __('Alertes en cours') }}</a>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <x-affichage-controles />
            <div class="flex flex-wrap items-center gap-2">
                @auth
                    <a href="{{ Auth::user()->homeUrl() }}" class="tn-btn tn-btn--primary">{{ __('Mon espace') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="tn-btn tn-btn--secondary">{{ __('Log Out') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="tn-btn tn-btn--secondary">{{ __('Se connecter') }}</a>
                    <a href="{{ route('register') }}" class="tn-btn tn-btn--primary">{{ __('Créer mon compte') }}</a>
                @endauth
            </div>
        </div>
    </nav>
</div>
