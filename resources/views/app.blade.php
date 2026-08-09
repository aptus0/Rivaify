<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{--
            Per-route SEO: each Inertia::render() call in routes/web.php
            passes a `seo` prop (title/description/canonical). Rendering it
            here — in the server-generated HTML, before Inertia hydrates —
            is what makes it actually crawlable by search engines and OG
            scrapers; Inertia's client-side <Head> component (used inside
            each page for client-side navigations) can't retroactively fix
            a wrong/missing tag in the *initial* HTML response. No Inertia
            SSR process needed for this — only the very first load matters
            for crawlers, and that's always a real Blade render.
        --}}
        @php
            $seo = $page['props']['seo'] ?? [];
            $title = $seo['title'] ?? 'Rivaify | Yeni Nesil E-Ticaret Platformu';
            $description = $seo['description'] ?? 'Rivaify ile online mağazanı kur, ürünlerini ve siparişlerini yönet, sosyal satış kanallarını tek platform üzerinden yönet.';
            $canonical = $seo['canonical'] ?? 'https://rivaify.com/';
        @endphp

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <meta name="theme-color" content="#FF6B00">
        <link rel="canonical" href="{{ $canonical }}">

        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:site_name" content="Rivaify">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:locale" content="tr_TR">
        <meta property="og:image" content="https://rivaify.com/og-image.png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="https://rivaify.com/og-image.png">

        @if ($seo['schema'] ?? null)
            <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/main.tsx'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
