<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark'=> ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-JZ32VTRQSP"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-JZ32VTRQSP');
    </script>

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="/favicon.png?v=4">
    <link rel="icon" href="/favicon.ico?v=4" sizes="any">
    <link rel="icon" href="/favicon.svg?v=4" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=4">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>