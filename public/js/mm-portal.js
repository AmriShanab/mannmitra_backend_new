// MannMitra portal: theme toggle (#themeToggle with <i id="themeIcon">).
(function () {
    var html = document.documentElement;
    var btn = document.getElementById('themeToggle');
    var icon = document.getElementById('themeIcon');
    if (!btn) return;

    function paint(theme) {
        if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        btn.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
    }
    paint(html.getAttribute('data-theme'));

    btn.addEventListener('click', function () {
        var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        try { localStorage.setItem('mm_theme', next); } catch (e) {}
        paint(next);
    });
})();
