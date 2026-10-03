<nav x-data="{ open: false }"
     @keydown.escape.window="if (open) { open = false; $refs.burger.focus() }"
     aria-label="{{ __('Navigation principale') }}"
     class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-between gap-y-2 min-h-[4rem]">
            <div class="flex flex-wrap">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ Auth::user()->homeUrl() }}" aria-label="{{ config('app.name') }} — {{ __('accueil') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden flex-wrap gap-x-8 gap-y-1 lg:-my-px lg:ms-10 lg:flex">
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
                    @elseif (Auth::user()->isAgent())
                        <x-nav-link :href="route('agent.index')" :active="request()->routeIs('agent.index')">
                            Espace agent
                        </x-nav-link>
                        <x-nav-link :href="route('agent.demandes.index')" :active="request()->routeIs('agent.demandes.*')">
                            {{ __('Centre technique municipal') }}
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
                            Mes demandes
                            <x-compteur-changements :nombre="$changementsNonVus" />
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
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 transition ease-in-out duration-150"
                                aria-haspopup="true" x-bind:aria-expanded="open">
                            <div>{{ Auth::user()->name }}</div>
                            <span class="ms-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ Auth::user()->role->label() }}</span>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger : bouton atteignable au clavier, ouvre le menu ; Échap le referme et rend le focus au bouton. -->
            <div class="-me-2 flex items-center lg:hidden">
                <button type="button" x-ref="burger" @click="open = ! open"
                        aria-controls="menu-mobile" x-bind:aria-expanded="open" aria-label="{{ __('Menu') }}"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-gray-900 hover:bg-gray-100 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div id="menu-mobile" :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.index')">
                    Administration
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.comptes.index')" :active="request()->routeIs('admin.comptes.*')">
                    {{ __('Comptes') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.services.index')" :active="request()->routeIs('admin.services.*')">
                    {{ __('Services') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.synchronisation.index')" :active="request()->routeIs('admin.synchronisation.*')">
                    {{ __('Synchronisation') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.alertes.index')" :active="request()->routeIs('admin.alertes.*')">
                    {{ __('Alertes') }}
                </x-responsive-nav-link>
            @elseif (Auth::user()->isAgent())
                <x-responsive-nav-link :href="route('agent.index')" :active="request()->routeIs('agent.index')">
                    Espace agent
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('agent.demandes.index')" :active="request()->routeIs('agent.demandes.*')">
                    {{ __('Centre technique municipal') }}
                    @if ($demandesEnAttente !== null)
                        <x-compteur-en-attente :nombre="$demandesEnAttente" />
                    @endif
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('agent.donnees-api.index')" :active="request()->routeIs('agent.donnees-api.*')">
                    {{ __('Données API') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('agent.journal.index')" :active="request()->routeIs('agent.journal.*')">
                    {{ __('Journal d\'activité') }}
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('demandes.index')" :active="request()->routeIs('demandes.*')">
                    Mes demandes
                    <x-compteur-changements :nombre="$changementsNonVus" />
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                    {{ __('Services') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('urgences.index')" :active="request()->routeIs('urgences.*')">
                    {{ __('Urgences') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->isCitoyen())
                <x-responsive-nav-link :href="route('contact.create')" :active="request()->routeIs('contact.*')">
                    {{ __('Contacter la mairie') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ Auth::user()->role->label() }}</span>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
