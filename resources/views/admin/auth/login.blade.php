<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - {{ config('app.name', 'StyleBite') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #241922;
            --muted: #73626c;
            --line: rgba(36, 25, 34, 0.12);
            --pink: #ff557a;
            --orange: #ff8a57;
            --panel: rgba(255, 255, 255, 0.88);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--ink);
            font-family: Manrope, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 18% 14%, rgba(255, 85, 122, 0.18), transparent 28%),
                radial-gradient(circle at 86% 78%, rgba(255, 138, 87, 0.18), transparent 30%),
                linear-gradient(135deg, #fff8f5 0%, #f8edf1 100%);
        }

        .login-shell {
            width: min(100%, 440px);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 28px 70px rgba(90, 48, 64, 0.18);
            padding: 34px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, var(--pink), var(--orange));
        }

        h1 {
            margin: 0;
            font-size: 28px;
            line-height: 1.15;
            letter-spacing: 0;
        }

        p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        form { margin-top: 28px; }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 800;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            height: 48px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0 14px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            outline: none;
        }

        input:focus {
            border-color: rgba(255, 85, 122, 0.55);
            box-shadow: 0 0 0 4px rgba(255, 85, 122, 0.12);
        }

        .field { margin-bottom: 18px; }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin: 4px 0 22px;
            color: var(--muted);
            font-size: 13px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
        }

        button {
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 8px;
            color: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(135deg, var(--pink), var(--orange));
            box-shadow: 0 14px 26px rgba(255, 85, 122, 0.26);
        }

        .error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid rgba(190, 35, 65, 0.18);
            border-radius: 8px;
            color: #8b1730;
            background: rgba(255, 85, 122, 0.10);
            font-size: 13px;
            font-weight: 700;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <div class="brand">
            <div class="brand-mark">SB</div>
            <div>
                <h1>Admin Login</h1>
                <p>Sign in with an active admin account.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>

            <div class="row">
                <label class="remember" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1">
                    Remember me
                </label>
            </div>

            <button type="submit">Log in</button>
        </form>

        <a class="back-link" href="{{ route('home') }}">Back to website</a>
    </main>
</body>
</html>
