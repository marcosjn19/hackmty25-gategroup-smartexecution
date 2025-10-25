<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Etagate - Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        etagate: {
                            blue: '#2B3D4D',
                            'blue-dark': '#1a2835',
                            orange: '#FF7700',
                            'orange-dark': '#e66a00'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --etagate-blue: #2B3D4D;
            --etagate-blue-dark: #1a2835;
            --etagate-orange: #FF7700;
            --etagate-orange-dark: #e66a00;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>

<body class="bg-gray-50">
    <div x-data="{
            isMobile: window.innerWidth < 768,
            sidebarOpen: window.innerWidth >= 768
        }"
        @resize.window="isMobile = window.innerWidth < 768; sidebarOpen = !isMobile;"
        class="flex h-screen overflow-hidden">

        <!-- Mobile overlay -->
        <div x-show="sidebarOpen && isMobile" @click="sidebarOpen = false"
            x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 md:hidden"></div>

        <!-- Sidebar -->
        <aside x-cloak x-show="sidebarOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
            class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-white shadow-2xl flex flex-col border-r border-gray-200">

            <!-- Sidebar header (logo centered) -->
            <div class="relative flex items-center h-20 px-6 border-b border-gray-200">
                <img src="/etagate.svg" alt="Etagate Logo" class="h-8 w-auto mx-auto block" />
                <button @click="sidebarOpen = false"
                    class="md:hidden absolute right-6 top-1/2 -translate-y-1/2 text-gray-600 hover:text-etagate-orange focus:outline-none transition-colors duration-200"
                    aria-label="Close sidebar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-4 py-8 overflow-y-auto">
                <ul class="space-y-2">
                    <!-- Dashboard -->
                    <li>
                        <a href="{{ route('dashboard') }}"
                           class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform
                           {{ request()->routeIs('dashboard') ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50' }}">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300
                                {{ request()->routeIs('dashboard') ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10' }}">
                                <svg class="w-5 h-5 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange' }}"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Dashboard</span>
                        </a>
                    </li>

                    <!-- Models -->
                    <li>
                        <a href="{{ route('models.index') }}"
                           class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform
                           {{ request()->routeIs('models.*') ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50' }}">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300
                                {{ request()->routeIs('models.*') ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10' }}">
                                <svg class="w-5 h-5 {{ request()->routeIs('models.*') ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange' }}"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Models</span>
                        </a>
                    </li>

                    <!-- Processes (placeholder) -->
                    <li>
                        <a href="#"
                           class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform
                           text-gray-700 hover:text-etagate-orange hover:bg-gray-50">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300 bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10">
                                <svg class="w-5 h-5 text-gray-600 group-hover:text-etagate-orange"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Processes</span>
                        </a>
                    </li>

                    <!-- Add the rest (Run, History, Surveys, Settings) similarly if needed -->
                </ul>
            </nav>

            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center px-4 py-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                    <div class="w-10 h-10 bg-gradient-to-br from-etagate-orange to-orange-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                        JD
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-etagate-blue">John Doe</p>
                        <p class="text-xs text-gray-500">Admin</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Top header -->
            <header class="bg-white border-b border-gray-200 h-20">
                <div class="h-full flex items-center justify-between px-6">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen"
                            class="text-etagate-blue hover:text-etagate-orange focus:outline-none focus:ring-2 focus:ring-etagate-orange rounded-lg p-2 transition-colors duration-200"
                            aria-label="Toggle sidebar">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <!-- Title from slot or from a Blade section -->
                        <div class="ml-4 text-2xl font-bold text-etagate-blue">
                            @isset($header)
                                {{ $header }}
                            @else
                                @yield('page_title', 'Dashboard')
                            @endisset
                        </div>
                    </div>

                    <button
                        class="px-6 py-2.5 bg-gradient-to-r from-etagate-orange to-orange-600 text-white font-semibold rounded-full hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                        Get Started
                    </button>
                </div>
            </header>

            <!-- Page content -->
            <div class="p-8">
                <div class="max-w-7xl mx-auto">
                    @isset($slot)
                        {{ $slot }}
                    @else
                        @yield('content')
                    @endisset
                </div>
            </div>
        </main>
    </div>
</body>
</html>
