<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    {{-- Dipakai unggahan gambar di editor teks, yang memakai `fetch` biasa.
         Inertia sendiri tidak membutuhkannya: axios membaca cookie XSRF-TOKEN
         dan memasang headernya sendiri. `fetch` tidak melakukan itu, dan yang
         muncul tanpa baris ini adalah 419 tanpa penjelasan. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name') }}</title>

    {{-- Tema dipilih sebelum CSS terpasang, bukan setelah Vue hidup: kalau
         menunggu Vue, halaman berkedip terang dulu di setiap muat. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('dwf.theme');
                var dark = saved ? saved === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    {{-- @fonts memasang preload + @font-face untuk Inter / Roboto / Plus Jakarta
         Sans yang di-self-host `laravel-vite-plugin`. Tanpa baris ini, token
         --font-* menunjuk ke family yang tidak pernah dimuat, dan seluruh
         backoffice diam-diam jatuh ke font sistem. --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
