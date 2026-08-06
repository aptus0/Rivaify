<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Rivaify | Yeni Nesil E-Ticaret Platformu</title>
        <meta name="description" content="Rivaify ile online mağazanı kur, Instagram, Facebook ve TikTok satış kanallarını tek platformdan yönet.">
        <meta name="theme-color" content="#FF6B00">
        <link rel="canonical" href="https://rivaify.com/">

        <!-- Open Graph -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="https://rivaify.com/">
        <meta property="og:site_name" content="Rivaify">
        <meta property="og:title" content="Rivaify | Yeni Nesil E-Ticaret Platformu">
        <meta property="og:description" content="Rivaify ile online mağazanı kur, Instagram, Facebook ve TikTok satış kanallarını tek platformdan yönet.">
        <meta property="og:locale" content="tr_TR">
        <meta property="og:image" content="https://rivaify.com/og-image.png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">

        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Rivaify | Yeni Nesil E-Ticaret Platformu">
        <meta name="twitter:description" content="Rivaify ile online mağazanı kur, Instagram, Facebook ve TikTok satış kanallarını tek platformdan yönet.">
        <meta name="twitter:image" content="https://rivaify.com/og-image.png">

        {{-- Raw "@context"/"@type" keys in literal Blade text get misparsed as
             directives (Blade's @-token scanner isn't JSON-aware) — building
             the object in PHP and echoing via json_encode sidesteps that. --}}
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Rivaify',
            'url' => 'https://rivaify.com/',
            'logo' => 'https://rivaify.com/og-image.png',
            'description' => 'Rivaify ile online mağazanı kur, Instagram, Facebook ve TikTok satış kanallarını tek platformdan yönet.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/main.tsx'])
    </head>
    <body class="antialiased">
        <div id="root"></div>
    </body>
</html>
