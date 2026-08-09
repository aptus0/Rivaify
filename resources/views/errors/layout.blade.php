@php
    use Illuminate\Support\Str;

    $status = (int) ($status ?? 500);
    $requestUid = request()->attributes->get('rivaify_request_uid')
        ?: 'RVF-'.now()->format('YmdHis').'-'.Str::upper((string) Str::ulid());
    request()->attributes->set('rivaify_request_uid', $requestUid);

    $copy = [
        400 => ['title' => 'İstek tamamlanamadı', 'message' => 'Bir şeyler eksik ya da beklenenden farklı görünüyor. Sayfayı yenileyip tekrar deneyebilirsin.', 'accent' => '#f59e0b'],
        401 => ['title' => 'Oturum açman gerekiyor', 'message' => 'Bu sayfaya devam etmek için hesabına giriş yapmalısın.', 'accent' => '#f59e0b'],
        402 => ['title' => 'İşlem için ödeme gerekiyor', 'message' => 'Bu alana erişmek için ödeme ya da abonelik durumunun güncellenmesi gerekiyor.', 'accent' => '#f59e0b'],
        403 => ['title' => 'Bu alana erişimin yok', 'message' => 'Bu sayfa için gerekli yetkiye sahip değilsin. Hesap yöneticinle görüşebilirsin.', 'accent' => '#ef4444'],
        404 => ['title' => 'Aradığın sayfayı bulamadık', 'message' => 'Bağlantı değişmiş, sayfa taşınmış ya da yanlış yazılmış olabilir.', 'accent' => '#71717a'],
        405 => ['title' => 'Bu işlem desteklenmiyor', 'message' => 'Bu sayfada yapmak istediğin işlem şu anda kullanılamıyor.', 'accent' => '#f59e0b'],
        408 => ['title' => 'Bağlantı çok uzun sürdü', 'message' => 'İstek beklenenden uzun sürdü. Birazdan tekrar deneyebilirsin.', 'accent' => '#f59e0b'],
        409 => ['title' => 'Bir çakışma oluştu', 'message' => 'Bu içerik yakın zamanda değişmiş olabilir. Sayfayı yenileyip tekrar devam edebilirsin.', 'accent' => '#f59e0b'],
        419 => ['title' => 'Oturum süren doldu', 'message' => 'Güvenliğin için oturum yenilenmeli. Sayfayı yenileyip tekrar deneyebilirsin.', 'accent' => '#f59e0b'],
        422 => ['title' => 'Bilgileri kontrol etmelisin', 'message' => 'Bazı bilgiler eksik ya da hatalı görünüyor. Formu kontrol edip tekrar deneyebilirsin.', 'accent' => '#f59e0b'],
        423 => ['title' => 'Bu alan şu an meşgul', 'message' => 'İlgili kayıt kısa süreliğine kilitlenmiş olabilir. Birazdan tekrar deneyebilirsin.', 'accent' => '#f59e0b'],
        425 => ['title' => 'Biraz sonra tekrar dene', 'message' => 'Bu işlem şu anda tamamlanamıyor. Kısa süre sonra yeniden deneyebilirsin.', 'accent' => '#f59e0b'],
        426 => ['title' => 'Güncelleme gerekiyor', 'message' => 'Devam etmek için bağlantını veya tarayıcını güncellemen gerekebilir.', 'accent' => '#f59e0b'],
        429 => ['title' => 'Çok hızlı gidiyoruz', 'message' => 'Kısa sürede çok fazla işlem yapıldı. Biraz bekleyip tekrar deneyebilirsin.', 'accent' => '#f59e0b'],
        500 => ['title' => 'Bir şeyler yolunda gitmedi', 'message' => 'İşlemi şu anda tamamlayamadık. Merak etme, ekibimiz bu destek koduyla durumu inceleyebilir.', 'accent' => '#ef4444'],
        501 => ['title' => 'Bu özellik henüz hazır değil', 'message' => 'Açmak istediğin özellik şu anda kullanıma açık değil.', 'accent' => '#71717a'],
        502 => ['title' => 'Geçici bir bağlantı sorunu var', 'message' => 'Rivaify şu anda gerekli yanıta ulaşamadı. Birazdan tekrar deneyebilirsin.', 'accent' => '#ef4444'],
        503 => ['title' => 'Kısa bir bakım molası', 'message' => 'Rivaify kısa süreli bakımda ya da yoğunluk altında. Birazdan yeniden deneyebilirsin.', 'accent' => '#f59e0b'],
        504 => ['title' => 'Yanıt gecikti', 'message' => 'İşlem beklenenden uzun sürdü. Biraz sonra tekrar deneyebilirsin.', 'accent' => '#ef4444'],
    ];

    $data = $copy[$status] ?? $copy[500];
    $host = request()->getHost();
    $homeUrl = str_starts_with($host, 'app.') ? 'https://app.rivaify.com/dashboard' : 'https://rivaify.com/';
    $supportSubject = rawurlencode("Rivaify destek kodu: {$requestUid}");
    $supportBody = rawurlencode("Destek kodu: {$requestUid}");
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="rivaify-request-uid" content="{{ $requestUid }}">
    <title>{{ $data['title'] }} · Rivaify</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --accent: {{ $data['accent'] }};
            --orange: #ff6b00;
            --ink: #111111;
            --muted: #666a73;
            --line: rgba(17, 17, 17, .1);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 18% 20%, rgba(255, 107, 0, .18), transparent 31%),
                radial-gradient(circle at 83% 24%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 28%),
                linear-gradient(135deg, #fffaf4 0%, #f7f7f3 48%, #ffffff 100%);
            color: var(--ink);
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            pointer-events: none;
            border-radius: 999px;
            filter: blur(8px);
            opacity: .72;
            animation: float 8s ease-in-out infinite;
        }

        body::before {
            width: 240px;
            height: 240px;
            right: -70px;
            top: 16%;
            background: rgba(255, 107, 0, .14);
        }

        body::after {
            width: 190px;
            height: 190px;
            left: -58px;
            bottom: 14%;
            background: color-mix(in srgb, var(--accent) 16%, transparent);
            animation-delay: -2.4s;
        }

        .shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
        }

        .card {
            position: relative;
            width: min(760px, 100%);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, .75);
            border-radius: 28px;
            background: rgba(255, 255, 255, .82);
            box-shadow: 0 28px 90px rgba(15, 23, 42, .12);
            backdrop-filter: blur(22px);
            isolation: isolate;
        }

        .card::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(120deg, rgba(255,255,255,.82), rgba(255,255,255,.36), rgba(255,107,0,.08));
        }

        .shine {
            position: absolute;
            inset: -2px;
            background: linear-gradient(115deg, transparent 0%, transparent 35%, rgba(255,255,255,.75) 46%, transparent 58%, transparent 100%);
            transform: translateX(-72%);
            animation: shine 5.8s ease-in-out infinite;
            pointer-events: none;
        }

        .content {
            position: relative;
            padding: clamp(30px, 6vw, 58px);
            text-align: center;
        }

        .logo {
            display: block;
            width: min(190px, 62vw);
            height: auto;
            margin: 0 auto 34px;
        }

        .orb {
            width: 112px;
            height: 112px;
            display: grid;
            place-items: center;
            margin: 0 auto 26px;
            border-radius: 50%;
            color: white;
            font-size: 32px;
            font-weight: 900;
            background:
                linear-gradient(135deg, var(--orange), var(--accent)),
                var(--orange);
            box-shadow: 0 18px 52px color-mix(in srgb, var(--accent) 34%, transparent);
            animation: breathe 2.8s ease-in-out infinite;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: var(--orange);
            font-size: 12px;
            font-weight: 850;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(32px, 5vw, 58px);
            line-height: 1.02;
            letter-spacing: 0;
        }

        .message {
            max-width: 580px;
            margin: 18px auto 0;
            color: var(--muted);
            font-size: clamp(15px, 2vw, 18px);
            line-height: 1.72;
        }

        .support {
            width: fit-content;
            max-width: 100%;
            margin: 28px auto 0;
            padding: 12px 16px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(255, 255, 255, .76);
            color: #353941;
            font-size: 13px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .support strong {
            font-weight: 800;
            color: var(--ink);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 30px;
        }

        .button {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0 20px;
            border: 1px solid rgba(17, 17, 17, .14);
            background: rgba(255, 255, 255, .8);
            color: var(--ink);
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(15, 23, 42, .1);
            background: white;
        }

        .button.primary {
            border-color: var(--orange);
            background: var(--orange);
            color: white;
            box-shadow: 0 14px 32px rgba(255, 107, 0, .24);
        }

        .fineprint {
            margin-top: 26px;
            color: #8a8f98;
            font-size: 12px;
        }

        @keyframes shine {
            0%, 56% { transform: translateX(-72%); opacity: 0; }
            68% { opacity: .78; }
            100% { transform: translateX(72%); opacity: 0; }
        }

        @keyframes breathe {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.045); }
        }

        @keyframes float {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(0, -18px, 0) scale(1.04); }
        }

        @media (max-width: 640px) {
            .shell { padding: 16px; }
            .card { border-radius: 22px; }
            .content { padding: 30px 22px; }
            .orb { width: 92px; height: 92px; font-size: 28px; }
            .actions { flex-direction: column; }
            .button { width: 100%; }
        }
    </style>
</head>
<body>
<main class="shell">
    <section class="card" aria-labelledby="error-title">
        <div class="shine"></div>
        <div class="content">
            <img class="logo" src="/build/assets/rivaify-logo-horizontal-B_Mp0IMw.png" alt="Rivaify">
            <div class="orb" aria-hidden="true">{{ $status }}</div>
            <p class="eyebrow">Rivaify</p>
            <h1 id="error-title">{{ $data['title'] }}</h1>
            <p class="message">{{ $data['message'] }}</p>

            <div class="support">
                <strong>Destek kodu:</strong> {{ $requestUid }}
            </div>

            <div class="actions">
                <a class="button primary" href="{{ $homeUrl }}">Ana sayfaya dön</a>
                <a class="button" href="javascript:history.back()">Geri dön</a>
                <a class="button" href="mailto:destek@rivaify.com?subject={{ $supportSubject }}&body={{ $supportBody }}">Destek al</a>
            </div>

            <p class="fineprint">İstersen biraz sonra tekrar deneyebilirsin.</p>
        </div>
    </section>
</main>
</body>
</html>
