<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            /* Prevent pull-to-refresh and bounce on tablet */
            html, body { overscroll-behavior: none; }
        </style>
    </head>
    <body class="h-screen overflow-hidden bg-zinc-100 dark:bg-zinc-900">
        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @stack('scripts')
    </body>
</html>
