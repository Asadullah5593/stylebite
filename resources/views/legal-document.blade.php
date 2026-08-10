<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }} - {{ config('app.name', 'StyleBite') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#241922; --muted:#73626c; --line:rgba(36,25,34,.12); --pink:#ff557a; --orange:#ff8a57; }
        * { box-sizing: border-box; }
        body {
            margin:0; padding:32px 20px 64px; color:var(--ink);
            font-family: Manrope, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: linear-gradient(135deg,#fff8f5 0%,#f8edf1 100%);
        }
        .shell { max-width: 760px; margin: 0 auto; }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; }
        .brand-mark {
            width:44px; height:44px; display:grid; place-items:center; border-radius:10px;
            color:#fff; font-weight:800; background:linear-gradient(135deg,var(--pink),var(--orange));
        }
        h1 { margin:0 0 6px; font-size:clamp(24px,4vw,32px); letter-spacing:-.02em; }
        .meta { color:var(--muted); font-size:13px; font-weight:700; }
        .card {
            margin-top:24px; padding:clamp(20px,4vw,36px); background:rgba(255,255,255,.9);
            border:1px solid var(--line); border-radius:14px; box-shadow:0 20px 50px rgba(90,48,64,.10);
        }
        p { line-height:1.7; font-size:15px; white-space:pre-line; }
        p:first-child { margin-top:0; }
        a.back { display:inline-block; margin-top:24px; color:var(--muted); font-size:13px; font-weight:800; text-decoration:none; }
    </style>
</head>
<body>
    <main class="shell">
        <div class="brand">
            <div class="brand-mark">SB</div>
            <div>
                <h1>{{ $document->title }}</h1>
                <div class="meta">
                    Version {{ $document->version }}
                    @if ($document->published_at) · effective {{ $document->published_at->format('j F Y') }} @endif
                </div>
            </div>
        </div>

        <div class="card">
            @foreach ($document->paragraphs() as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <a class="back" href="{{ route('home') }}">← Back to Stylebite</a>
    </main>
</body>
</html>
