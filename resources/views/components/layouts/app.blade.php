<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    @include('components.layouts.partials.head')
    @fluxAppearance
</head>

<body class="text-primary-900 flex flex-col min-h-screen" x-data>
    <div id="app" class="flex flex-col grow">
        <x-layouts.app.header class="shrink-0" />

        <div class="grow">
            <div class="mx-auto">
                {{ $slot }}
            </div>
        </div>

        <x-layouts.app.footer class="shrink-0" />

        @include('components.layouts.partials.tail')
    </div>
    <x-impersonate::banner />
    @fluxScripts
</body>

</html>
