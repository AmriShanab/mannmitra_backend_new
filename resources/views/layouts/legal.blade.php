<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') – MannMitra</title>
    <meta name="description" content="@yield('description')">
    <link rel="icon" type="image/png" href="{{ asset('images/app_icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sky: #56b4ea;
            --indigo: #5b5fe6;
            --grad: linear-gradient(135deg, #56b4ea 0%, #5b5fe6 100%);
            --ink: #1a1c3a;
            --muted: #5e6385;
            --bg: #f4f6ff;
            --card: #ffffff;
            --line: #e4e8f9;
            --soft: #eef1ff;
            --danger-bg: #fff1f2;
            --danger-line: #f5b5bb;
            --danger-ink: #9f1d2b;
            --ph-bg: #fff3a3;
            --ph-ink: #5c4a00;
            --shadow: 0 10px 40px -12px rgba(60, 64, 160, .22);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #eceeff;
                --muted: #a4a9d0;
                --bg: #0d0f24;
                --card: #151833;
                --line: #262a52;
                --soft: #1c2045;
                --danger-bg: #2a1320;
                --danger-line: #6b2a3a;
                --danger-ink: #ffb3bf;
                --ph-bg: #4d4310;
                --ph-ink: #ffe98a;
                --shadow: 0 10px 40px -12px rgba(0, 0, 0, .6);
            }
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: 90px; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font-family: "Plus Jakarta Sans", -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.75; font-size: 16px; -webkit-font-smoothing: antialiased;
        }
        a { color: var(--indigo); text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
        @media (prefers-color-scheme: dark) { a { color: var(--sky); } }

        /* Reading progress */
        .progress { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: var(--grad); z-index: 100; }

        /* Top bar */
        .topbar {
            position: sticky; top: 0; z-index: 50;
            backdrop-filter: saturate(180%) blur(16px); -webkit-backdrop-filter: saturate(180%) blur(16px);
            background: color-mix(in srgb, var(--bg) 78%, transparent);
            border-bottom: 1px solid var(--line);
        }
        .topbar-inner { max-width: 1160px; margin: 0 auto; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 12px; color: var(--ink); font-weight: 800; font-size: 1.1rem; letter-spacing: -.01em; }
        .brand:hover { text-decoration: none; }
        .brand img { width: 38px; height: 38px; border-radius: 10px; box-shadow: 0 4px 14px rgba(91, 95, 230, .35); }
        .nav { display: flex; gap: 6px; background: var(--soft); padding: 4px; border-radius: 999px; }
        .nav a { padding: 7px 16px; border-radius: 999px; font-size: .88rem; color: var(--muted); font-weight: 600; }
        .nav a:hover { text-decoration: none; color: var(--ink); }
        .nav a.active { background: var(--grad); color: #fff; box-shadow: 0 4px 14px rgba(91, 95, 230, .4); }

        /* Hero */
        .hero { position: relative; overflow: hidden; background: var(--grad); color: #fff; padding: 72px 24px 130px; text-align: center; }
        .hero::before, .hero::after { content: ""; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .12); filter: blur(2px); }
        .hero::before { width: 420px; height: 420px; top: -180px; left: -120px; }
        .hero::after { width: 320px; height: 320px; bottom: -160px; right: -80px; background: rgba(255, 255, 255, .1); }
        .hero-inner { position: relative; max-width: 760px; margin: 0 auto; }
        .hero-logo { width: 96px; height: 96px; border-radius: 26px; box-shadow: 0 18px 40px rgba(20, 20, 90, .4), 0 0 0 6px rgba(255, 255, 255, .18); }
        .eyebrow { display: inline-block; margin: 26px 0 12px; padding: 6px 16px; border-radius: 999px; background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .hero h1 { margin: 0 0 14px; font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 800; letter-spacing: -.025em; line-height: 1.12; }
        .hero p.sub { margin: 0 auto; max-width: 600px; font-size: 1.08rem; opacity: .92; }
        .meta { margin-top: 26px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .meta span { padding: 7px 14px; border-radius: 12px; background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .25); font-size: .86rem; font-weight: 600; }
        .meta mark.ph { background: rgba(255, 243, 163, .95); color: #4a3b00; }

        /* Layout */
        .wrap { max-width: 1160px; margin: -70px auto 0; padding: 0 24px 80px; position: relative; display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 32px; align-items: start; }
        .toc { position: sticky; top: 88px; background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 20px 14px; box-shadow: var(--shadow); max-height: calc(100vh - 110px); overflow: auto; }
        .toc h4 { margin: 0 10px 10px; font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
        .toc a { display: flex; gap: 10px; align-items: baseline; padding: 8px 10px; border-radius: 10px; color: var(--muted); font-size: .88rem; font-weight: 600; line-height: 1.4; }
        .toc a:hover { background: var(--soft); text-decoration: none; color: var(--ink); }
        .toc a.on { background: var(--soft); color: var(--indigo); }
        @media (prefers-color-scheme: dark) { .toc a.on { color: var(--sky); } }
        .toc a b { min-width: 20px; color: var(--sky); font-weight: 800; font-size: .8rem; }

        .mobile-toc { display: none; }
        .content { counter-reset: sec; min-width: 0; }

        /* Section cards */
        .sec { counter-increment: sec; background: var(--card); border: 1px solid var(--line); border-radius: 22px; padding: 30px 34px; margin-bottom: 20px; box-shadow: var(--shadow); }
        .sec h2 { display: flex; align-items: center; gap: 14px; margin: 0 0 16px; font-size: 1.35rem; font-weight: 800; letter-spacing: -.015em; line-height: 1.3; }
        .sec h2::before { content: counter(sec); flex: 0 0 auto; width: 38px; height: 38px; border-radius: 12px; background: var(--grad); color: #fff; display: grid; place-items: center; font-size: .95rem; font-weight: 800; box-shadow: 0 6px 16px rgba(91, 95, 230, .35); }
        .sec h3 { margin: 24px 0 10px; font-size: 1.02rem; font-weight: 700; color: var(--indigo); }
        @media (prefers-color-scheme: dark) { .sec h3 { color: var(--sky); } }
        .sec p { margin: 0 0 12px; color: var(--muted); }
        .sec strong { color: var(--ink); }
        .sec ul { margin: 0 0 12px; padding: 0; list-style: none; }
        .sec li { position: relative; padding: 6px 0 6px 28px; color: var(--muted); }
        .sec li::before { content: ""; position: absolute; left: 4px; top: 15px; width: 10px; height: 10px; border-radius: 50%; background: var(--grad); }

        /* Callouts */
        .callout { display: flex; gap: 14px; padding: 18px 20px; border-radius: 16px; background: var(--soft); border: 1px solid var(--line); margin: 14px 0; }
        .callout .ic { flex: 0 0 auto; font-size: 1.4rem; line-height: 1.4; }
        .callout > div > :last-child { margin-bottom: 0; }
        .callout.danger { background: var(--danger-bg); border-color: var(--danger-line); }
        .callout.danger strong, .callout.danger li { color: var(--danger-ink); }
        .callout.danger li::before { background: var(--danger-ink); }

        /* Helplines */
        .helplines { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-top: 14px; }
        .helpline { padding: 14px 16px; border-radius: 14px; background: var(--card); border: 1px solid var(--danger-line); }
        .helpline small { display: block; color: var(--muted); font-size: .78rem; font-weight: 600; }
        .helpline b { display: block; font-size: 1rem; color: var(--danger-ink); }
        .helpline span { font-size: .8rem; color: var(--muted); }

        /* Card grid */
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; margin: 12px 0 6px; }
        .card { padding: 18px; border-radius: 16px; border: 1px solid var(--line); background: var(--bg); }
        .card .emoji { font-size: 1.5rem; }
        .card h4 { margin: 8px 0 4px; font-size: .98rem; }
        .card p { margin: 0; font-size: .9rem; }

        /* Chips */
        .chips { display: flex; flex-wrap: wrap; gap: 10px; margin: 8px 0 14px; }
        .chip { padding: 8px 14px; border-radius: 999px; background: var(--soft); border: 1px solid var(--line); font-size: .85rem; font-weight: 700; color: var(--ink); }
        .chip small { color: var(--muted); font-weight: 600; }

        /* Price table */
        .prices { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 14px 0; }
        .price { padding: 20px; border-radius: 16px; border: 1px solid var(--line); background: var(--bg); text-align: center; }
        .price small { color: var(--muted); font-weight: 600; }
        .price .amt { margin-top: 6px; font-size: 1.7rem; font-weight: 800; background: var(--grad); -webkit-background-clip: text; background-clip: text; color: transparent; }

        /* Placeholders */
        mark.ph { background: var(--ph-bg); color: var(--ph-ink); padding: 1px 7px; border-radius: 6px; font-weight: 700; font-size: .92em; border: 1px dashed #d9b500; }

        /* Footer */
        .cta { text-align: center; padding: 30px; border-radius: 22px; background: var(--grad); color: #fff; box-shadow: var(--shadow); }
        .cta h3 { margin: 0 0 6px; color: #fff; font-size: 1.25rem; }
        .cta p { margin: 0 0 16px; color: rgba(255, 255, 255, .9); }
        .cta a.btn { display: inline-block; padding: 11px 26px; border-radius: 999px; background: #fff; color: var(--indigo); font-weight: 700; }
        .cta a.btn:hover { text-decoration: none; transform: translateY(-1px); }
        footer { text-align: center; color: var(--muted); font-size: .85rem; padding: 0 24px 40px; }
        footer img { width: 28px; height: 28px; border-radius: 8px; vertical-align: middle; margin-right: 8px; }

        @media (max-width: 920px) {
            .wrap { grid-template-columns: 1fr; margin-top: -60px; }
            .toc { display: none; }
            .mobile-toc { display: block; background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 6px 18px; margin-bottom: 20px; box-shadow: var(--shadow); }
            .mobile-toc summary { cursor: pointer; padding: 12px 0; font-weight: 700; }
            .mobile-toc a { display: block; padding: 8px 0; color: var(--muted); font-size: .92rem; }
            .mobile-toc a b { color: var(--sky); margin-right: 8px; }
        }
        @media (max-width: 600px) {
            .topbar-inner { padding: 10px 16px; }
            .brand span { display: none; }
            .hero { padding: 52px 18px 110px; }
            .wrap { padding: 0 14px 60px; }
            .sec { padding: 22px 18px; border-radius: 18px; }
            .sec h2 { font-size: 1.15rem; }
        }
        @media print {
            .topbar, .toc, .mobile-toc, .progress, .cta { display: none; }
            .hero { background: #fff; color: #000; padding: 20px 0; }
            .wrap { display: block; margin: 0; }
            .sec { box-shadow: none; break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="progress" id="progress"></div>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ url('/') }}">
            <img src="{{ asset('images/app_icon.png') }}" alt="MannMitra logo">
            <span>MannMitra</span>
        </a>
        <nav class="nav">
            <a href="{{ route('privacy.policy') }}" class="{{ request()->routeIs('privacy.policy') ? 'active' : '' }}">Privacy Policy</a>
            <a href="{{ route('terms.and.conditions') }}" class="{{ request()->routeIs('terms.and.conditions') ? 'active' : '' }}">Terms &amp; Conditions</a>
        </nav>
    </div>
</header>

<section class="hero">
    <div class="hero-inner">
        <img class="hero-logo" src="{{ asset('images/app_icon.png') }}" alt="MannMitra">
        <div class="eyebrow">@yield('eyebrow')</div>
        <h1>@yield('heading')</h1>
        <p class="sub">@yield('subtitle')</p>
        <div class="meta">
            <span>Last updated: <mark class="ph">[DATE]</mark></span>
            <span>Applies to the MannMitra app &amp; WhatsApp service</span>
        </div>
    </div>
</section>

<main class="wrap">
    <aside class="toc" id="toc">
        <h4>On this page</h4>
    </aside>

    <div class="content">
        <details class="mobile-toc" id="mobileToc">
            <summary>On this page</summary>
        </details>

        @yield('content')

        <div class="cta">
            <h3>@yield('cta_title')</h3>
            <p>@yield('cta_text')</p>
            <a class="btn" href="@yield('cta_url')">@yield('cta_button')</a>
        </div>
    </div>
</main>

<footer>
    <img src="{{ asset('images/app_icon.png') }}" alt="">
    &copy; {{ date('Y') }} <mark class="ph">[COMPANY / LEGAL NAME]</mark>. All rights reserved. MannMitra is not an emergency service.
</footer>

<script>
    (function () {
        var secs = Array.prototype.slice.call(document.querySelectorAll('.sec'));
        var toc = document.getElementById('toc');
        var mtoc = document.getElementById('mobileToc');
        var links = [];
        secs.forEach(function (s, i) {
            var h = s.querySelector('h2');
            if (!s.id) s.id = 's' + (i + 1);
            var label = '<b>' + (i + 1) + '</b>' + h.textContent;
            var a = document.createElement('a'); a.href = '#' + s.id; a.innerHTML = label;
            toc.appendChild(a); links.push(a);
            var m = document.createElement('a'); m.href = '#' + s.id; m.innerHTML = label;
            m.addEventListener('click', function () { mtoc.open = false; });
            mtoc.appendChild(m);
        });
        var bar = document.getElementById('progress');
        function onScroll() {
            var d = document.documentElement;
            bar.style.width = (d.scrollTop / (d.scrollHeight - d.clientHeight) * 100) + '%';
            var cur = 0;
            secs.forEach(function (s, i) { if (s.getBoundingClientRect().top < 140) cur = i; });
            links.forEach(function (a, i) { a.classList.toggle('on', i === cur); });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();
</script>
</body>
</html>
