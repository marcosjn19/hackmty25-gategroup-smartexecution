{{-- resources/views/dashboard.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-etagate-blue">Dashboard</h2>
    </x-slot>

    <!-- Hero -->
    <div class="bg-gradient-to-br from-etagate-blue to-[#1a2835] rounded-3xl p-12 mb-8 text-white shadow-2xl">
        <div class="flex flex-col md:flex-row items-center justify-between">
            <div class="md:w-1/2 mb-8 md:mb-0">
                <div class="inline-block px-4 py-2 bg-white bg-opacity-10 rounded-full text-sm font-semibold mb-6">
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
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-bold mb-1">60% Faster Processing</h3>
                            <p class="text-gray-300">Reduce preparation time with AI-guided workflows</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div
                            class="w-12 h-12 bg-etagate-orange rounded-full flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
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
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
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

    <!-- Stat cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div
            class="bg-white rounded-2xl shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-etagate-blue">Total Processes</h3>
                <div
                    class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
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
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
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
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
            </div>
            <p
                class="text-4xl font-bold bg-gradient-to-r from-etagate-blue to-etagate-orange bg-clip-text text-transparent">
                45</p>
            <p class="text-sm text-blue-600 font-semibold mt-2">5 new responses</p>
        </div>
    </div>
</x-app-layout>
