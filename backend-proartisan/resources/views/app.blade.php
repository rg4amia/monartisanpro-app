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
    @if (session()->has('impersonator_id'))
        @php($impersonator = \App\Models\User::find(session('impersonator_id')))
        <div style="position:fixed;top:0;left:0;right:0;z-index:2147483647;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px;padding:8px 16px;background:#7d2f14;color:#fff;font-size:13px;line-height:1.4;box-shadow:0 2px 8px rgba(0,0,0,.25)">
            <span>
                Usurpation de session &mdash; vous consultez le compte de
                <strong>{{ auth()->user()?->name ?? 'utilisateur' }}</strong>
                @if ($impersonator)
                    (initiée par {{ $impersonator->name }})
                @endif
            </span>
            <form method="POST" action="{{ route('admin.stop-impersonating') }}" style="margin:0">
                @csrf
                <button type="submit" style="cursor:pointer;border:0;border-radius:8px;padding:5px 12px;background:#fff;color:#7d2f14;font-weight:700;font-size:12px">
                    Revenir à mon compte
                </button>
            </form>
        </div>
    @endif
    @inertia
</body>

</html>