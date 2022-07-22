<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'FOSSBilling') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <!-- Scripts -->
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>

<body class="font-sans antialiased">
    <div class="min-h-full">
        <nav x-data="{ open: false }" class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <x-fossbilling-logo class="block h-[40px]" />
                        </div>
                        <div class="hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8">
                        </div>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:items-center">
                        <!-- Client Login -->
                        <button type="button"
                            class="inline-flex items-center px-4 py-2 border border-foss text-base font-medium rounded-md shadow-sm text-foss bg-white hover:border-b-4 focus:outline-none">Sign
                            In</button>
                    </div>
                    <div class="-mr-2 flex items-center sm:hidden">
                        <!-- Client Login -->
                        <button type="button"
                            class="inline-flex items-center px-4 py-2 border border-foss text-base font-medium rounded-md shadow-sm text-foss bg-white hover:border-b-4 focus:outline-none">Sign
                            In</button>

                        <!-- Mobile menu button -->
                        <button type="button"
                            class="bg-white inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            aria-controls="mobile-menu" @click="open = !open" aria-expanded="false"
                            x-bind:aria-expanded="open.toString()">
                            <span class="sr-only">Open main menu</span>
                            <svg x-state:on="Menu open" x-state:off="Menu closed" class="h-6 w-6 block"
                                :class="{ 'hidden': open, 'block': !(open) }"
                                x-description="Heroicon name: outline/menu" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            <svg x-state:on="Menu open" x-state:off="Menu closed" class="h-6 w-6 hidden"
                                :class="{ 'block': open, 'hidden': !(open) }" x-description="Heroicon name: outline/x"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div x-description="Mobile menu, show/hide based on menu state." class="sm:hidden" id="mobile-menu"
                x-show="open" style="display: none;">
                <div class="pt-2 pb-3 space-y-1">

                    <a href="#"
                        class="bg-blue-50 border-blue-500 text-blue-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
                        aria-current="page" x-state:on="Current" x-state:off="Default"
                        x-state-description="Current: &quot;bg-blue-50 border-blue-500 text-blue-700&quot;, Default: &quot;border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800&quot;">
                        Dashboard
                    </a>

                    <a href="#"
                        class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
                        x-state-description="undefined: &quot;bg-blue-50 border-blue-500 text-blue-700&quot;, undefined: &quot;border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800&quot;">
                        Team
                    </a>

                    <a href="#"
                        class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
                        x-state-description="undefined: &quot;bg-blue-50 border-blue-500 text-blue-700&quot;, undefined: &quot;border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800&quot;">
                        Projects
                    </a>

                    <a href="#"
                        class="border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800 block pl-3 pr-4 py-2 border-l-4 text-base font-medium"
                        x-state-description="undefined: &quot;bg-blue-50 border-blue-500 text-blue-700&quot;, undefined: &quot;border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800&quot;">
                        Calendar
                    </a>

                </div>
            </div>
        </nav>

        <div class="py-10">
            <main>
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>

</html>
