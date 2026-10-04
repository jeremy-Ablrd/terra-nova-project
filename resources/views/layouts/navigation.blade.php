{{-- Barre latérale unique pour les trois rôles : le menu dépend du rôle, rien d'autre. Sur petit écran, elle se replie
     derrière un bouton « Menu » (Échap la referme et rend le focus au bouton) ; sans JavaScript, elle reste dépliée. --}}
<header x-data="{ open: false }"
        @keydown.escape.window="if (open) { open = false; $refs.burger.focus() }"
        class="no-print bg-white border-b border-gray-200 lg:border-b-0 lg:border-r lg:w-64 lg:shrink-0 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
    <nav aria-label="{{ __('Navigation principale') }}" class="flex flex-col lg:min-h-full">
        <div class="flex items-center justify-between gap-2 px-4 py-3">
            <a href="{{ Auth::user()->homeUrl() }}" class="flex items-center gap-2 min-h-[2.75rem] font-display font-bold text-xl" aria-label="{{ config('app.name') }} — {{ __('accueil') }}">
                <x-application-logo class="block h-9 w-9 fill-current text-brand" />
                <span>Terra Nova</span>
            </a>

            <!-- Menu : bouton atteignable au clavier, ouvre le menu ; Échap le referme et rend le focus au bouton. -->
            <div class="flex items-center lg:hidden">
                <button type="button" x-ref="burger" @click="open = ! open"
                        aria-controls="menu-mobile" x-bind:aria-expanded="open" aria-label="{{ __('Menu') }}"
                        class="tn-btn tn-btn--secondary min-w-[2.75rem]">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="menu-mobile" :class="{'flex': open, 'hidden': ! open}" class="hidden lg:flex flex-col flex-1 gap-6 px-3 pb-4">
            <div class="flex flex-col gap-1">
                @if (Auth::user()->isAdmin())
                    <x-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.index')">
                        Administration
                    </x-nav-link>
                    <x-nav-link :href="route('admin.comptes.index')" :active="request()->routeIs('admin.comptes.*')">
                        {{ __('Comptes') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.services.index')" :active="request()->routeIs('admin.services.*')">
                        {{ __('Services') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.synchronisation.index')" :active="request()->routeIs('admin.synchronisation.*')">
                        {{ __('Synchronisation') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.alertes.index')" :active="request()->routeIs('admin.alertes.*')">
                        {{ __('Alertes') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.participation.index')" :active="request()->routeIs('admin.participation.*')">
                        {{ __('Participation') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.securite')" :active="request()->routeIs('admin.securite')">
                        {{ __('Sécurité') }}
                    </x-nav-link>
                @elseif (Auth::user()->isAgent())
                    <x-nav-link :href="route('agent.index')" :active="request()->routeIs('agent.index')">
                        Espace agent
                    </x-nav-link>
                    <x-nav-link :href="route('agent.demandes.index')" :active="request()->routeIs('agent.demandes.*')">
                        <span>{{ __('Centre technique municipal') }}</span>
                        @if ($demandesEnAttente !== null)
                            <x-compteur-en-attente :nombre="$demandesEnAttente" />
                        @endif
                    </x-nav-link>
                    <x-nav-link :href="route('agent.donnees-api.index')" :active="request()->routeIs('agent.donnees-api.*')">
                        {{ __('Données API') }}
                    </x-nav-link>
                    <x-nav-link :href="route('agent.journal.index')" :active="request()->routeIs('agent.journal.*')">
                        {{ __('Journal d\'activité') }}
                    </x-nav-link>
                @else
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('demandes.index')" :active="request()->routeIs('demandes.*')">
                        <span>Mes demandes</span>
                        <x-compteur-changements :nombre="$changementsNonVus" />
                    </x-nav-link>
                    <x-nav-link :href="route('projets.index')" :active="request()->routeIs('projets.*', 'idees.*')">
                        {{ __('Participer') }}
                    </x-nav-link>
                    <x-nav-link :href="route('mes-contributions.index')" :active="request()->routeIs('mes-contributions.*')">
                        {{ __('Mes contributions') }}
                    </x-nav-link>
                    <x-nav-link :href="route('mes-donnees.index')" :active="request()->routeIs('mes-donnees.*')">
                        {{ __('Mes données') }}
                    </x-nav-link>
                    <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                        {{ __('Services') }}
                    </x-nav-link>
                    <x-nav-link :href="route('urgences.index')" :active="request()->routeIs('urgences.*')">
                        {{ __('Urgences') }}
                    </x-nav-link>
                @endif
                @if (Auth::user()->isCitoyen())
                    <x-nav-link :href="route('contact.create')" :active="request()->routeIs('contact.*')">
                        {{ __('Contacter la mairie') }}
                    </x-nav-link>
                @endif
            </div>

            <div class="mt-auto flex flex-col gap-4">
                <x-affichage-controles class="flex-col !items-start" />

                <div class="border-t border-gray-200 pt-4 flex flex-col gap-1 px-1">
                    <p class="font-semibold">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-gray-600">{{ Auth::user()->role->label() }}</p>
                    <a href="{{ route('profile.edit') }}" class="text-sm underline">{{ __('Profile') }}</a>
                    <a href="{{ route('mes-connexions.index') }}" class="text-sm underline gap-2">
                        {{ __('Mes connexions') }}
                        <x-compteur-connexions :nombre="$alertesConnexion" />
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm underline min-h-[1.5rem]">{{ __('Log Out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
</header>
