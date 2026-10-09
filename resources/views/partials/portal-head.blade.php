{{-- Shared <head> assets for the MannMitra portal pages (login, listener, doctor). --}}
<link rel="icon" type="image/png" href="{{ asset('images/app_icon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/mm-portal.css') }}">
<script>
    // Apply the saved (or system) theme before first paint to avoid a flash.
    (function () {
        var t = null;
        try { t = localStorage.getItem('mm_theme'); } catch (e) {}
        if (!t) t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
</script>
