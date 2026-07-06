<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'StyleBite') }} - Fashion Social App</title>
    <meta name="description" content="StyleBite is a fashion-first social app for sharing looks, reels, contests, memories, and style inspiration.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #251b22;
            --muted: #6f6069;
            --soft: #fff8f6;
            --line: rgba(37, 27, 34, 0.12);
            --pink: #ff557a;
            --orange: #ff8a57;
            --green: #25b96f;
            --shadow: 0 24px 70px rgba(93, 45, 63, 0.17);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            font-family: Manrope, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 14% 18%, rgba(255, 85, 122, 0.16), transparent 28%),
                radial-gradient(circle at 88% 12%, rgba(37, 185, 111, 0.12), transparent 24%),
                linear-gradient(135deg, #fffaf8 0%, #f7edf1 100%);
        }

        a { color: inherit; }

        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header,
        main,
        .below {
            width: min(1120px, calc(100% - 40px));
            margin: 0 auto;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 24px 0 14px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 20px;
        }

        .mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, var(--pink), var(--orange));
            box-shadow: 0 12px 24px rgba(255, 85, 122, 0.26);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 800;
        }

        nav a { text-decoration: none; }

        main {
            flex: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 430px);
            align-items: center;
            gap: 54px;
            padding: 44px 0 34px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.72);
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 0 5px rgba(37, 185, 111, 0.12);
        }

        h1 {
            max-width: 720px;
            margin: 22px 0 18px;
            font-size: clamp(42px, 7vw, 82px);
            line-height: 0.96;
            letter-spacing: 0;
        }

        .lead {
            max-width: 620px;
            margin: 0;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.7;
        }

        .store-links {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .store-link {
            min-width: 178px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 13px 16px;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            background: #1f1820;
            box-shadow: 0 16px 30px rgba(31, 24, 32, 0.18);
        }

        .store-link.play {
            background: linear-gradient(135deg, #1f1820 0%, #293326 100%);
        }

        .store-icon {
            width: 26px;
            height: 26px;
            display: grid;
            place-items: center;
            font-size: 20px;
            line-height: 1;
        }

        .store-copy {
            display: grid;
            gap: 1px;
        }

        .store-copy small {
            color: rgba(255, 255, 255, 0.72);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .store-copy strong {
            font-size: 15px;
            line-height: 1.1;
        }

        .phone {
            position: relative;
            min-height: 580px;
            padding: 16px;
            border: 1px solid rgba(37, 27, 34, 0.10);
            border-radius: 36px;
            background: #1b151c;
            box-shadow: var(--shadow);
        }

        .screen {
            height: 100%;
            min-height: 548px;
            overflow: hidden;
            border-radius: 26px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent 24%),
                linear-gradient(150deg, #342332 0%, #161116 54%, #322218 100%);
            color: #fff;
            padding: 22px;
        }

        .screen-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 800;
        }

        .avatar-row {
            display: flex;
            gap: 8px;
        }

        .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.75);
            background: linear-gradient(135deg, var(--pink), var(--orange));
        }

        .look-card {
            min-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            border-radius: 8px;
            padding: 18px;
            background:
                linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.52) 100%),
                linear-gradient(135deg, #fa8ca1 0%, #f3c06d 46%, #49c58c 100%);
        }

        .look-card h2 {
            margin: 0 0 8px;
            font-size: 30px;
            line-height: 1.05;
            letter-spacing: 0;
        }

        .look-card p {
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.5;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
        }

        .chip {
            padding: 8px 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.82);
            font-size: 12px;
            font-weight: 800;
        }

        .below {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            padding: 0 0 36px;
        }

        .feature {
            border-top: 1px solid var(--line);
            padding-top: 18px;
        }

        .feature strong {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .feature span {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        @media (max-width: 860px) {
            header {
                align-items: flex-start;
            }

            nav {
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            main {
                grid-template-columns: 1fr;
                padding-top: 28px;
            }

            .phone {
                min-height: auto;
            }

            .screen {
                min-height: 440px;
            }

            .below {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            header,
            main,
            .below {
                width: min(100% - 28px, 1120px);
            }

            header {
                display: grid;
            }

            nav {
                justify-content: flex-start;
                gap: 12px;
            }

            .store-link {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <a class="brand" href="{{ route('home') }}" aria-label="{{ config('app.name', 'StyleBite') }} home">
                <span class="mark">SB</span>
                <span>{{ config('app.name', 'StyleBite') }}</span>
            </a>

            <nav aria-label="Main navigation">
                <a href="{{ route('privacy-policy') }}">Privacy</a>
                <a href="{{ route('delete-account') }}">Delete account</a>
                <a href="{{ route('admin.home') }}">Admin</a>
            </nav>
        </header>

        <main>
            <section>
                <div class="eyebrow"><span class="dot"></span> Fashion, reels, contests, and memories</div>
                <h1>StyleBite</h1>
                <p class="lead">Discover outfit ideas, share your looks, join fashion contests, save style memories, and connect with creators who make getting dressed feel fresh every day.</p>

                <div class="store-links" aria-label="Download app links">
                    <a class="store-link" href="{{ $appStoreUrl }}" aria-label="Download StyleBite on the App Store">
                        <span class="store-icon">A</span>
                        <span class="store-copy">
                            <small>Download on the</small>
                            <strong>App Store</strong>
                        </span>
                    </a>

                    <a class="store-link play" href="{{ $playStoreUrl }}" aria-label="Get StyleBite on Google Play">
                        <span class="store-icon">P</span>
                        <span class="store-copy">
                            <small>Get it on</small>
                            <strong>Google Play</strong>
                        </span>
                    </a>
                </div>
            </section>

            <aside class="phone" aria-label="StyleBite app preview">
                <div class="screen">
                    <div class="screen-top">
                        <span>Today</span>
                        <div class="avatar-row">
                            <span class="avatar"></span>
                            <span class="avatar"></span>
                            <span class="avatar"></span>
                        </div>
                    </div>

                    <div class="look-card">
                        <h2>Streetwear moodboard</h2>
                        <p>Vote, comment, save, and share the looks that match your style.</p>
                    </div>

                    <div class="chips">
                        <span class="chip">Style contests</span>
                        <span class="chip">Creator reels</span>
                        <span class="chip">Saved memories</span>
                        <span class="chip">Live trends</span>
                    </div>
                </div>
            </aside>
        </main>

        <section class="below" aria-label="StyleBite features">
            <div class="feature">
                <strong>Post your fits</strong>
                <span>Publish photos, reels, ratings, comments, and shares from one social fashion feed.</span>
            </div>
            <div class="feature">
                <strong>Join contests</strong>
                <span>Compete in style challenges, team up, invite creators, and climb leaderboards.</span>
            </div>
            <div class="feature">
                <strong>Keep memories</strong>
                <span>Save favorite looks, media, and searches so your inspiration is always close.</span>
            </div>
        </section>
    </div>
</body>
</html>
