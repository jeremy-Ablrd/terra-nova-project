{{-- Navigation des visiteurs non connectés (pages publiques passant par le layout commun, ex. /alertes). --}}
<div class="bg-white border-b border-gray-200">
    <nav aria-label="{{ __('Navigation principale') }}" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 min-h-[4.5rem] flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
        <a href="{{ url('/') }}" class="flex items-center gap-2 min-h-[2.75rem] font-bold text-xl font-display">
            <x-application-logo class="h-8 w-auto fill-current text-brand" />
            Terra Nova
        </a>

        <div class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
            <a href="{{ route('services.index') }}" class="nav-lien" @if (request()->routeIs('services.*')) aria-current="page" @endif>{{ __('Services') }}</a>
            <a href="{{ route('urgences.index') }}" class="nav-lien" @if (request()->routeIs('urgences.*')) aria-current="page" @endif>{{ __('Urgences') }}</a>
            <a href="{{ route('alertes.index') }}" class="nav-lien" @if (request()->routeIs('alertes.*')) aria-current="page" @endif>{{ __('Alertes en cours') }}</a>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('login') }}" class="btn btn-secondary">{{ __('Se connecter') }}</a>
            <a href="{{ route('register') }}" class="btn btn-primary">{{ __('Créer mon compte') }}</a>
        </div>
    </nav>
</div>
