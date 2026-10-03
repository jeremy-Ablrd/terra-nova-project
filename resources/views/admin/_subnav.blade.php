<nav aria-label="{{ __('Navigation de l\'administration') }}" class="flex flex-wrap gap-x-4 gap-y-1 text-sm border-b border-gray-200 pb-3">
    <a href="{{ route('admin.index') }}" class="{{ request()->routeIs('admin.index') ? 'font-semibold text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">Accueil</a>
    <a href="{{ route('admin.comptes.index') }}" class="{{ request()->routeIs('admin.comptes.*') ? 'font-semibold text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">Comptes</a>
    <a href="{{ route('admin.services.index') }}" class="{{ request()->routeIs('admin.services.*') ? 'font-semibold text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">{{ __('Services') }}</a>
    <a href="{{ route('admin.synchronisation.index') }}" class="{{ request()->routeIs('admin.synchronisation.*') ? 'font-semibold text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">{{ __('Synchronisation') }}</a>
    <a href="{{ route('admin.alertes.index') }}" class="{{ request()->routeIs('admin.alertes.*') ? 'font-semibold text-gray-900' : 'text-gray-600 hover:text-gray-900' }}">{{ __('Alertes') }}</a>
</nav>
