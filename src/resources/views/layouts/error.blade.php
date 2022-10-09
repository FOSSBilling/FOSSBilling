<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <!-- Disabled responsiveness until it stops looking ugly -->
    <!-- <meta name="viewport" content="width=device-width, initial-scale=1"> -->

    <title>{{ $title }} | {{ config('app.name', 'FOSSBilling') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-900">

    <!-- Page content -->
    <div class="text-gray-50 flex min-h-screen flex-col items-center justify-center py-2">
        <!-- Title -->
        <p class="text-3xl font-medium text-orange-400">ERROR {{ $error_code }}</p>
        <h1 class="text-6xl font-medium mt-5 mb-5">
            {{ $context }}
        </h1>

        <!-- Content -->
        <div class="w-4/5">
            <!-- Rounded box -->
            <div class="h-96 p-4 rounded-3xl mt-16 bg-[#252F49] grid grid-cols-2 divide-x-2 divide-gray-200">
                <!-- Left -->
                <div>
                    <!-- Left title -->
                    <p class="text-3xl font-medium mt-10 text-center">I'm an <b>administrator</b></p>
                    <div class="flex flex-col items-center">
                        <div class="w-4/5">
                            <p class="text-2xl mt-10 text-center">{{ $admin_helptext }}</p>
                        </div>
                    </div>
                    @isset($admin_action_link)
                    <a href="{{ $admin_action_link }}">
                        <p class="text-2xl text-center mt-16 font-medium hover:text-blue-400">{{ $admin_action_text }} ➔</p>
                    </a>
                    @endisset
                </div>
                <!-- Right -->
                <div>
                    <!-- Right title -->
                    <p class="text-3xl font-medium mt-10 text-center">I'm a <b>visitor</b></p>
                    <div class="flex flex-col items-center justify-center">
                        <div class="w-4/5">
                            <p class="text-2xl mt-10 text-center">There should be a website here soon. 👀<br />Please try visiting later.</p>
                        </div>
                    </div>
                    @isset($visitor_action_link)
                    <a href="{{ $visitor_action_link }}">
                        <p class="text-2xl text-center mt-16 font-medium hover:text-blue-400">{{ $visitor_action_text }} ➔</p>
                    </a>
                    @endisset
                </div>
            </div>
            <p class="text-2xl text-right font-medium mt-5">Need more help? Visit <a class="text-orange-400 hover:text-orange-500" href="https://fossbilling/e/{{ $error_code }}/" target="_blank">fossbilling.org/e/{{ $error_code }}</a></p>
        </div>

        <!-- Footer -->
        <div class="flex flex-col justify-center items-center mt-10">
            <div class="mb-3">
                <a href="https://fossbilling.org" target="_blank" rel="noopener"><img src="{{ url('/img/logo-white.svg') }}" width="402" height="86" /></a>
            </div>
            <div>
                <p class="text-gray-200">
                    <a class="hover:text-blue-400" href="https://github.com/FOSSBilling/FOSSBilling" target="_blank" rel="noopener">source code</a> |
                    <a class="hover:text-blue-400" href="https://docs.fossbilling.org" target="_blank" rel="noopener">documentation</a> |
                    <a class="hover:text-blue-400" href="https://fossbilling.org/discord" target="_blank" rel="noopener">discord</a> |
                    <a class="hover:text-blue-400" href="https://fossbilling.org/donate" target="_blank" rel="noopener">donate</a>
                </p>
            </div>
        </div>

    </div>

</body>

</html>