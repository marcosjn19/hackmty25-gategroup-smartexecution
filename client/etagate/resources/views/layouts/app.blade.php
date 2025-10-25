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

        [x-cloak] {
            display: none !important
        }
    </style>
</head>

<body class="bg-gray-50">
    <div x-data="{
                    activeItem: 'dashboard',
                    isMobile: window.innerWidth < 768,
                    sidebarOpen: window.innerWidth >= 768
                }" @resize.window=" isMobile = window.innerWidth < 768; sidebarOpen = !isMobile;" class="flex h-screen overflow-hidden">
        <div x-show="sidebarOpen && isMobile" @click="sidebarOpen = false"
            x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 md:hidden"></div>

        <aside x-cloak x-show="sidebarOpen" x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-white shadow-2xl flex flex-col border-r border-gray-200">
            <div class="relative flex items-center h-20 px-6 border-b border-gray-200">
                <img src="/etagate.svg" alt="Etagate Logo" class="h-8 w-auto mx-auto block" />
                <button @click="sidebarOpen = false"
                    class="md:hidden absolute right-6 top-1/2 -translate-y-1/2 text-gray-600 hover:text-etagate-orange focus:outline-none transition-colors duration-200"
                    aria-label="Close sidebar">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <nav class="flex-1 px-4 py-8 overflow-y-auto">
                <ul class="space-y-2">
                    <li>
                        <button @click="activeItem = 'dashboard'"
                            :class="activeItem === 'dashboard' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'dashboard' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'dashboard' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                                    </path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Dashboard</span>
                        </button>
                    </li>

                    <li>
                        <button @click="activeItem = 'models'"
                            :class="activeItem === 'models' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'models' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'models' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                                    </path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Models</span>
                        </button>
                    </li>

                    <li>
                        <button @click="activeItem = 'processes'"
                            :class="activeItem === 'processes' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'processes' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'processes' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                                    </path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Processes</span>
                        </button>
                    </li>

                    <li>
                        <button @click="activeItem = 'run'"
                            :class="activeItem === 'run' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'run' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'run' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Run</span>
                        </button>
                    </li>

                    <li>
                        <button @click="activeItem = 'history'"
                            :class="activeItem === 'history' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'history' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'history' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">History</span>
                        </button>
                    </li>

                    <li>
                        <button @click="activeItem = 'surveys'"
                            :class="activeItem === 'surveys' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'surveys' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'surveys' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                                    </path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Surveys</span>
                        </button>
                    </li>

                    <li>
                        <button @click="activeItem = 'settings'"
                            :class="activeItem === 'settings' ? 'bg-gradient-to-r from-etagate-orange to-orange-600 text-white shadow-lg scale-105' : 'text-gray-700 hover:text-etagate-orange hover:bg-gray-50'"
                            class="w-full flex items-center px-4 py-3.5 rounded-xl transition-all duration-300 group transform">
                            <div :class="activeItem === 'settings' ? 'bg-white bg-opacity-20' : 'bg-gray-100 group-hover:bg-etagate-orange group-hover:bg-opacity-10'"
                                class="w-10 h-10 rounded-lg flex items-center justify-center transition-all duration-300">
                                <svg :class="activeItem === 'settings' ? 'text-white' : 'text-gray-600 group-hover:text-etagate-orange'"
                                    class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                    </path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <span class="ml-3 font-semibold text-left">Settings</span>
                        </button>
                    </li>
                </ul>
            </nav>

            <div class="border-t border-gray-200 p-4">
                <div
                    class="flex items-center px-4 py-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                    <div
                        class="w-10 h-10 bg-gradient-to-br from-etagate-orange to-orange-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                        JD
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-semibold text-etagate-blue">John Doe</p>
                        <p class="text-xs text-gray-500">Admin</p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto bg-gray-50">
            <header class="bg-white border-b border-gray-200 h-20">
                <div class="h-full flex items-center justify-between px-6">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen"
                            class="text-etagate-blue hover:text-etagate-orange focus:outline-none focus:ring-2 focus:ring-etagate-orange rounded-lg p-2 transition-colors duration-200"
                            aria-label="Toggle sidebar">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <h1 class="ml-4 text-2xl font-bold text-etagate-blue capitalize" x-text="activeItem"></h1>
                    </div>

                    <button
                        class="px-6 py-2.5 bg-gradient-to-r from-etagate-orange to-orange-600 text-white font-semibold rounded-full hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                        Get Started
                    </button>
                </div>
            </header>

            <div class="p-8">
                <div class="max-w-7xl mx-auto">
                    <div
                        class="bg-gradient-to-br from-etagate-blue to-[#1a2835] rounded-3xl p-12 mb-8 text-white shadow-2xl">
                        <div class="flex flex-col md:flex-row items-center justify-between">
                            <div class="md:w-1/2 mb-8 md:mb-0">
                                <div
                                    class="inline-block px-4 py-2 bg-white bg-opacity-10 rounded-full text-sm font-semibold mb-6">
                                    Why Etagate
                                </div>
                                <h2 class="text-5xl font-bold mb-4">
                                    Operational <span class="text-etagate-orange">Excellence</span><br /> at Scale
                                </h2>
                                <p class="text-gray-300 text-lg mb-8">
                                    Eliminate manual errors, accelerate workflows, and maintain consistent quality
                                    across all global operations
                                </p>

                                <div class="space-y-6">
                                    <div class="flex items-start">
                                        <div
                                            class="w-12 h-12 bg-etagate-orange rounded-full flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-xl font-bold mb-1">60% Faster Processing</h3>
                                            <p class="text-gray-300">Reduce preparation time with AI-guided workflows
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <div
                                            class="w-12 h-12 bg-etagate-orange rounded-full flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                                </path>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-xl font-bold mb-1">95% Error Detection</h3>
                                            <p class="text-gray-300">Catch issues before they reach passengers</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <div
                                            class="w-12 h-12 bg-etagate-orange rounded-full flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-xl font-bold mb-1">Real-Time Validation</h3>
                                            <p class="text-gray-300">Instant feedback on every quality checkpoint</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="md:w-1/2 md:pl-12">
                                <div
                                    class="bg-gradient-to-br from-white to-gray-100 rounded-2xl p-12 text-center shadow-2xl border-4 border-etagate-orange border-opacity-30">
                                    <h3
                                        class="text-7xl font-bold bg-gradient-to-r from-etagate-blue to-etagate-orange bg-clip-text text-transparent mb-4">
                                        700M+
                                    </h3>
                                    <p class="text-2xl font-bold text-etagate-blue mb-2">Passengers Trust</p>
                                    <p class="text-gray-600">Our Quality Standards</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div
                            class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-etagate-blue">Total Processes</h3>
                                <div
                                    class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <p
                                class="text-4xl font-bold bg-gradient-to-r from-etagate-blue to-etagate-orange bg-clip-text text-transparent">
                                124</p>
                            <p class="text-sm text-green-600 font-semibold mt-2">↑ 12% this month</p>
                        </div>

                        <div
                            class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-etagate-blue">Active Models</h3>
                                <div
                                    class="w-12 h-12 bg-gradient-to-br from-etagate-orange to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <p
                                class="text-4xl font-bold bg-gradient-to-r from-etagate-blue to-etagate-orange bg-clip-text text-transparent">
                                18</p>
                            <p class="text-sm text-gray-500 font-semibold mt-2">3 under review</p>
                        </div>

                        <div
                            class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-etagate-blue">Surveys</h3>
                                <div
                                    class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <p
                                class="text-4xl font-bold bg-gradient-to-r from-etagate-blue to-etagate-orange bg-clip-text text-transparent">
                                45</p>
                            <p class="text-sm text-blue-600 font-semibold mt-2">5 new responses</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
