<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $shareUrl }}">
    <meta property="og:site_name" content="StyleBite">
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ $shareUrl }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        <meta property="og:image:secure_url" content="{{ $image }}">
        <meta name="twitter:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Manrope, "Segoe UI", sans-serif;
            color: #251b22;
            background: linear-gradient(160deg, #fff8f6, #f6ecef);
        }
        main {
            max-width: 440px;
            margin: 0 auto;
            padding: 28px 18px 40px;
        }
        .card {
            background: #fff;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(93, 45, 63, 0.12);
        }
        .media {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 5;
            object-fit: cover;
            background: #1b1418;
        }
        .empty {
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 700;
        }
        .body { padding: 18px 18px 22px; }
        .kind {
            margin: 0 0 8px;
            color: #ff557a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        h1 { margin: 0; font-size: 22px; line-height: 1.25; }
        p { margin: 10px 0 0; color: #6f6069; line-height: 1.45; }
        .actions { display: grid; gap: 10px; margin-top: 18px; }
        a.button {
            display: block;
            text-align: center;
            text-decoration: none;
            border-radius: 999px;
            padding: 14px 16px;
            font-weight: 800;
        }
        .open { color: #fff; background: linear-gradient(90deg, #ff557a, #ff8a57); }
        .store { color: #251b22; background: #fff3f0; }
    </style>
</head>
<body>
    <main>
        <article class="card">
            @if ($image)
                <img class="media" src="{{ $image }}" alt="{{ $title }}">
            @else
                <div class="media empty">StyleBite</div>
            @endif
            <div class="body">
                <p class="kind">{{ $kind }}</p>
                <h1>{{ $available ? ($author !== '' ? $author : 'StyleBite') : 'Open StyleBite' }}</h1>
                @if ($username !== '')
                    <p>&#64;{{ $username }}</p>
                @endif
                @if ($caption !== '')
                    <p>{{ $caption }}</p>
                @elseif (! $available)
                    <p>This link opens in the StyleBite app.</p>
                @endif
                <div class="actions">
                    <a class="button open" href="{{ $intentUrl }}">Open in StyleBite</a>
                    <a class="button store" href="{{ $playStoreUrl }}">Get it on Google Play</a>
                </div>
            </div>
        </article>
    </main>
    <script>
        (function () {
            var agent = navigator.userAgent || '';
            if (/bot|crawl|spider|preview|facebookexternalhit|WhatsApp|Telegram|Slackbot|Twitterbot|LinkedInBot/i.test(agent)) {
                return;
            }
            if (/Android/i.test(agent)) {
                window.location.href = @json($intentUrl);
            }
        })();
    </script>
</body>
</html>
