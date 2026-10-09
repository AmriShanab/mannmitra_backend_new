<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in – MannMitra</title>
    @include('partials.portal-head')
    <style>
        body { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); }
        .brand-panel { position: relative; overflow: hidden; background: var(--grad); color: #fff; display: flex; flex-direction: column; justify-content: space-between; padding: 48px 56px; }
        .brand-panel::before, .brand-panel::after { content: ""; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .12); }
        .brand-panel::before { width: 520px; height: 520px; top: -220px; left: -160px; }
        .brand-panel::after { width: 420px; height: 420px; bottom: -200px; right: -120px; background: rgba(255, 255, 255, .09); }
        .brand-panel > * { position: relative; z-index: 1; }
        .bp-logo { display: flex; align-items: center; gap: 14px; font-weight: 800; font-size: 1.3rem; }
        .bp-logo img { width: 52px; height: 52px; border-radius: 15px; box-shadow: 0 10px 26px rgba(20, 20, 90, .35), 0 0 0 4px rgba(255, 255, 255, .2); }
        .bp-copy h1 { font-size: clamp(2rem, 3.6vw, 3.1rem); font-weight: 800; letter-spacing: -.03em; line-height: 1.12; margin-bottom: 16px; }
        .bp-copy p { font-size: 1.05rem; opacity: .92; max-width: 460px; margin: 0 0 28px; }
        .roles { display: flex; flex-wrap: wrap; gap: 10px; }
        .roles span { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 999px; background: rgba(255, 255, 255, .16); border: 1px solid rgba(255, 255, 255, .28); font-weight: 700; font-size: .86rem; backdrop-filter: blur(8px); }
        .bp-foot { font-size: .8rem; opacity: .8; }

        .form-panel { display: flex; align-items: center; justify-content: center; padding: 40px 28px; position: relative; }
        .theme-corner { position: absolute; top: 20px; right: 20px; }
        .form-box { width: 100%; max-width: 410px; }
        .mobile-logo { display: none; align-items: center; gap: 12px; font-weight: 800; font-size: 1.15rem; margin-bottom: 28px; }
        .mobile-logo img { width: 44px; height: 44px; border-radius: 13px; box-shadow: 0 6px 18px rgba(91, 95, 230, .4); }
        .form-box h2 { font-size: 1.9rem; font-weight: 800; letter-spacing: -.025em; }
        .form-box .lead { color: var(--muted); margin: 6px 0 28px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: .8rem; font-weight: 700; margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .input-wrap > i.lead-ic { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--faint); pointer-events: none; }
        .input-wrap input { width: 100%; padding: 14px 46px 14px 44px; border-radius: 14px; border: 1.5px solid var(--line); background: var(--card); color: var(--ink); font: inherit; font-size: .95rem; outline: none; transition: .15s; }
        .input-wrap input:focus { border-color: var(--sky); box-shadow: 0 0 0 4px color-mix(in srgb, var(--sky) 22%, transparent); }
        .input-wrap input::placeholder { color: var(--faint); }
        .eye { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 36px; height: 36px; border: 0; background: none; color: var(--faint); cursor: pointer; border-radius: 10px; }
        .eye:hover { color: var(--indigo); background: var(--soft); }

        .remember { display: flex; align-items: center; gap: 10px; margin: 4px 0 24px; font-size: .88rem; color: var(--muted); cursor: pointer; user-select: none; }
        .remember input { width: 18px; height: 18px; accent-color: var(--indigo); cursor: pointer; }

        .alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: var(--danger-bg); color: var(--danger); font-size: .86rem; font-weight: 600; margin-bottom: 18px; }
        .form-box .btn-primary { padding: 14px; font-size: .98rem; border-radius: 14px; }
        .secure { display: flex; justify-content: center; gap: 8px; align-items: center; margin-top: 22px; color: var(--faint); font-size: .8rem; }
        .legal { margin-top: 26px; text-align: center; font-size: .8rem; color: var(--faint); }
        .legal a { font-weight: 600; }

        @media (max-width: 900px) {
            body { grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .mobile-logo { display: flex; }
            .form-panel { min-height: 100vh; }
        }
    </style>
</head>
<body>

<aside class="brand-panel">
    <div class="bp-logo">
        <img src="{{ asset('images/app_icon.png') }}" alt="MannMitra">
        MannMitra
    </div>

    <div class="bp-copy">
        <h1>Care that listens,<br>a team that cares.</h1>
        <p>The MannMitra professional portal: support people in real time through chat, voice and video, all in one calm workspace.</p>
        <div class="roles">
            <span><i class="fas fa-hands-helping"></i> Listeners</span>
            <span><i class="fas fa-user-doctor"></i> Doctors</span>
            <span><i class="fas fa-shield-heart"></i> Admins</span>
        </div>
    </div>

    <div class="bp-foot">&copy; {{ date('Y') }} MannMitra. Conversations here are confidential.</div>
</aside>

<main class="form-panel">
    <button type="button" class="icon-btn theme-corner" id="themeToggle" aria-label="Toggle dark/light mode"><i class="fas fa-moon" id="themeIcon"></i></button>

    <div class="form-box">
        <div class="mobile-logo">
            <img src="{{ asset('images/app_icon.png') }}" alt="MannMitra"> MannMitra
        </div>

        <h2>Welcome back</h2>
        <p class="lead">Sign in to continue to your workspace.</p>

        <form action="{{ route('admin.login.submit') }}" method="post" novalidate>
            @csrf

            @if($errors->any())
                <div class="alert" role="alert">
                    <i class="fas fa-circle-exclamation" style="margin-top:3px"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <div class="field">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <i class="far fa-envelope lead-ic"></i>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="username" required autofocus>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock lead-ic"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="eye" id="eye" aria-label="Show password"><i class="far fa-eye"></i></button>
                </div>
            </div>

            <label class="remember">
                <input type="checkbox" name="remember" value="1"> Keep me signed in on this device
            </label>

            <button type="submit" class="btn btn-primary btn-block">Sign in <i class="fas fa-arrow-right"></i></button>
        </form>

        <div class="secure"><i class="fas fa-shield-halved"></i> Secure, encrypted sign-in</div>
        <div class="legal">
            By signing in you agree to our <a href="{{ route('terms.and.conditions') }}">Terms</a> and <a href="{{ route('privacy.policy') }}">Privacy Policy</a>.
        </div>
    </div>
</main>

<script src="{{ asset('js/mm-portal.js') }}"></script>
<script>
    (function () {
        var pw = document.getElementById('password'), eye = document.getElementById('eye');
        eye.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            eye.innerHTML = '<i class="far ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
            eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    })();
</script>
</body>
</html>
