<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Notifications') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|space-grotesk:500,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(249,115,22,0.18),_transparent_32%),linear-gradient(180deg,_#fffaf5_0%,_#fffdf8_38%,_#f4efe7_100%)] font-sans text-slate-900">
        <div
            id="app"
            data-communications-page="{{ $communicationsPage ?? 'templates' }}"
        ></div>
        <script>
            window.__ACL_COMMUNICATIONS_UI__ = @json($aclCommunicationsUi ?? []);
        </script>
    </body>
</html>
