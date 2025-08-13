<nav x-data="{ open: false }" class="bg-base-100 border-b border-base-200">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Left Side: Logo, Site Title, and Tagline -->
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center mr-8">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-base-content" />
                    </a>
                </div>

                <!-- Site Title and Tagline -->
                <div class="hidden sm:flex sm:items-center" style="margin-left: 0.2rem;">
                    <div class="flex flex-col">
                        <h1 class="text-xl font-semibold text-base-content">
                            {{ \App\Models\Setting::getValue('site_title', 'RoutePilot Pro') }}
                        </h1>
                        <p class="text-base text-base-content/50">
                            {{ \App\Models\Setting::getValue('site_tagline', 'Professional Pool Service Management') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Side: Navigation Links and Profile -->
            <div class="flex items-center">
                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('locations.index')" :active="request()->routeIs('locations.*')">
                        {{ __('Locations') }}
                    </x-nav-link>
                    @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        {{ __('Reports') }}
                    </x-nav-link>
                    <x-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                        {{ __('Invoices') }}
                    </x-nav-link>
                    @endif
                    @if(auth()->user()->isCustomer())
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        {{ __('Reports') }}
                    </x-nav-link>
                    <x-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                        {{ __('Invoices') }}
                    </x-nav-link>
                    @endif
                    @if(auth()->user()->isAdmin())
                    <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                        {{ __('Clients') }}
                    </x-nav-link>
                    <x-nav-link :href="route('technicians.index')" :active="request()->routeIs('technicians.*')">
                        {{ __('Technicians') }}
                    </x-nav-link>
                    @endif
                </div>

                <!-- Profile Dropdown -->
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-base-content bg-base-100 hover:text-base-content/70 focus:outline-none transition ease-in-out duration-150">
                                <div class="flex items-center space-x-2">
                                    <!-- User Avatar -->
                                    <div class="w-8 h-8 rounded-full overflow-hidden">
                                        @if(Auth::user()->profile_photo)
                                            <img src="{{ asset(Storage::url(Auth::user()->profile_photo)) }}" alt="{{ Auth::user()->name }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full bg-primary text-primary-content flex items-center justify-center text-sm font-semibold">
                                                {{ substr(Auth::user()->first_name, 0, 1) }}{{ substr(Auth::user()->last_name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <span>{{ Auth::user()->name }}</span>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @if(auth()->user()->isAdmin())
                            <x-dropdown-link :href="route('admin.settings.index')">
                                {{ __('Site Settings') }}
                            </x-dropdown-link>
                            @endif

                            <!-- Dark Mode Toggle -->
                            <div class="px-4 py-2 text-sm text-base-content">
                                <button 
                                    x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
                                    x-init="
                                        // Initialize with current theme state
                                        darkMode = localStorage.getItem('darkMode') === 'true' || (localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
                                        
                                        $watch('darkMode', val => {
                                            localStorage.setItem('darkMode', val);
                                            if (val) {
                                                document.documentElement.setAttribute('data-theme', 'dark');
                                            } else {
                                                document.documentElement.setAttribute('data-theme', 'light');
                                            }
                                        })
                                    "
                                    @click="darkMode = !darkMode"
                                    class="flex items-center w-full text-left hover:bg-base-200 px-2 py-1 rounded transition-colors"
                                >
                                    <svg x-show="!darkMode" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                                    </svg>
                                    <svg x-show="darkMode" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                    <span x-text="darkMode ? 'Light Mode' : 'Dark Mode'"></span>
                                </button>
                            </div>

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

                <!-- Hamburger -->
                <div class="-me-2 flex items-center sm:hidden">
                    <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-base-content/70 hover:text-base-content hover:bg-base-200 focus:outline-none focus:bg-base-200 focus:text-base-content transition duration-150 ease-in-out">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('locations.index')" :active="request()->routeIs('locations.*')">
                {{ __('Locations') }}
            </x-responsive-nav-link>
            @if(auth()->user()->isAdmin() || auth()->user()->isTechnician())
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                {{ __('Reports') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                {{ __('Invoices') }}
            </x-responsive-nav-link>
            @endif
            @if(auth()->user()->isCustomer())
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                {{ __('Reports') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')">
                {{ __('Invoices') }}
            </x-responsive-nav-link>
            @endif
            @if(auth()->user()->isAdmin())
                <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    {{ __('Clients') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('technicians.index')" :active="request()->routeIs('technicians.*')">
                    {{ __('Technicians') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')">
                    {{ __('Site Settings') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-base-200">
            <div class="px-4">
                <div class="font-medium text-base text-base-content">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-base-content/70">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Dark Mode Toggle for Mobile -->
                <div class="px-4 py-2">
                    <button 
                        x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }"
                        x-init="
                            // Initialize with current theme state
                            darkMode = localStorage.getItem('darkMode') === 'true' || (localStorage.getItem('darkMode') === null && window.matchMedia('(prefers-color-scheme: dark)').matches);
                            
                            $watch('darkMode', val => {
                                localStorage.setItem('darkMode', val);
                                if (val) {
                                    document.documentElement.setAttribute('data-theme', 'dark');
                                } else {
                                    document.documentElement.setAttribute('data-theme', 'light');
                                }
                            })
                        "
                        @click="darkMode = !darkMode"
                        class="flex items-center w-full text-left hover:bg-base-200 px-2 py-1 rounded transition-colors text-base-content"
                    >
                        <svg x-show="!darkMode" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                        <svg x-show="darkMode" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span x-text="darkMode ? 'Light Mode' : 'Dark Mode'" class="text-sm font-medium"></span>
                    </button>
                </div>

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
