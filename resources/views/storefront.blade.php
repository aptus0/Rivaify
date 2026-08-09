<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <title>{{ $store->name }} · Rivaify</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/storefront/main.tsx'])
    </head>
    <body class="antialiased">
        <div id="root"></div>
    </body>
</html>
