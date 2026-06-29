<!DOCTYPE html>
@php
    $attributes = $attributes ?? new \Illuminate\View\ComponentAttributeBag();
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400..700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles        

        <script>
            if (localStorage.getItem('dark-mode') === 'false' || !('dark-mode' in localStorage)) {
                document.querySelector('html').classList.remove('dark');
                document.querySelector('html').style.colorScheme = 'light';
            } else {
                document.querySelector('html').classList.add('dark');
                document.querySelector('html').style.colorScheme = 'dark';
            }
        </script>
    </head>
    <body
        class="font-inter antialiased bg-gray-100 dark:bg-gray-900 text-gray-600 dark:text-gray-400"
        x-data="{ sidebarOpen: false }"
    >

        @php
        $sidebarVariant = $sidebarVariant ?? 'default';
        $headerVariant  = $headerVariant ?? 'default';
        $background     = $background ?? '';
        @endphp

        <!-- Page wrapper -->
        <div class="flex h-[100dvh] overflow-hidden">
            <x-app.sidebar :variant="$attributes->get('sidebarVariant', 'default')" />

            <div class="relative flex min-w-0 flex-1 flex-col overflow-y-auto overflow-x-hidden {{ $background }}" x-ref="contentarea">
                <x-app.header :variant="$attributes->get('headerVariant', 'default')" />

                <main class="grow">
                    {{-- Si la vista usa <x-app-layout> puede venir un slot llamado "header" --}}
                    @isset($header)
                        <div class="px-4 sm:px-6 lg:px-8 py-6">
                            {{ $header }}
                        </div>
                    @endisset

                    {{-- Compatibilidad: Blade Component ($slot) o Layout clásico (@yield) --}}
                    @isset($slot)
                        @yield('content')
                        {{ $slot }}
                    @else
                        @yield('content')
                    @endisset
                </main>


            </div>

        </div>

        @livewireScriptConfig
        @stack('scripts')
    </body>
</html>
