/*
 * MannMitra portal UI helpers: toast notifications and a styled confirm dialog.
 *   MM.toast('Saved', 'success')            types: success | error | warning | info
 *   if (await MM.confirm('Sure?', { title: 'Delete', okText: 'Delete', danger: true })) { ... }
 * window.alert() is routed to a toast so older code keeps working without a blocking popup.
 */
(function () {
    if (window.MM && window.MM.toast) return;

    var css = '' +
        '.mmui{--mmui-card:#fff;--mmui-ink:#1a1c3a;--mmui-muted:#5e6385;--mmui-line:#e4e8f9;--mmui-grad:linear-gradient(135deg,#56b4ea,#5b5fe6);' +
        '--mmui-ok:#12a37a;--mmui-err:#d6455d;--mmui-warn:#c77700;--mmui-info:#5b5fe6;font-family:"Plus Jakarta Sans",-apple-system,"Segoe UI",Roboto,Arial,sans-serif}' +
        '[data-theme="dark"] .mmui{--mmui-card:#1c1f3d;--mmui-ink:#eef0ff;--mmui-muted:#a4a9cc;--mmui-line:#2e3260}' +

        /* toasts */
        '#mmui-toasts{position:fixed;top:18px;right:18px;z-index:99999;display:flex;flex-direction:column;gap:10px;width:min(360px,calc(100vw - 36px));pointer-events:none}' +
        '.mmui-toast{pointer-events:auto;position:relative;display:flex;gap:12px;align-items:flex-start;padding:14px 14px 16px 14px;background:var(--mmui-card);color:var(--mmui-ink);' +
        'border:1px solid var(--mmui-line);border-radius:16px;box-shadow:0 18px 40px -12px rgba(40,44,120,.35);overflow:hidden;' +
        'transform:translateX(120%);opacity:0;transition:transform .45s cubic-bezier(.2,.9,.3,1.2),opacity .3s}' +
        '.mmui-toast.in{transform:none;opacity:1}.mmui-toast.out{transform:translateX(120%);opacity:0;transition:transform .3s ease-in,opacity .3s}' +
        '.mmui-ic{flex:none;width:34px;height:34px;border-radius:11px;display:grid;place-items:center;color:#fff;font-size:16px;font-weight:800;background:var(--c)}' +
        '.mmui-tx{flex:1;min-width:0}.mmui-tt{font-weight:800;font-size:14px;margin:1px 0 2px}.mmui-ms{font-size:13.5px;line-height:1.45;color:var(--mmui-muted);word-wrap:break-word}' +
        '.mmui-x{flex:none;border:0;background:none;color:var(--mmui-muted);font-size:18px;line-height:1;cursor:pointer;padding:2px 4px;border-radius:8px}.mmui-x:hover{background:var(--mmui-line)}' +
        '.mmui-bar{position:absolute;left:0;bottom:0;height:3px;width:100%;background:var(--c);transform-origin:left;animation:mmui-bar linear forwards}' +
        '@keyframes mmui-bar{to{transform:scaleX(0)}}' +
        '.mmui-success{--c:var(--mmui-ok)}.mmui-error{--c:var(--mmui-err)}.mmui-warning{--c:var(--mmui-warn)}.mmui-info{--c:var(--mmui-info)}' +

        /* confirm dialog */
        '.mmui-ov{position:fixed;inset:0;z-index:99998;display:grid;place-items:center;padding:18px;background:rgba(18,20,50,.5);backdrop-filter:blur(4px);opacity:0;transition:opacity .2s}' +
        '.mmui-ov.in{opacity:1}' +
        '.mmui-dlg{width:min(420px,100%);background:var(--mmui-card);color:var(--mmui-ink);border:1px solid var(--mmui-line);border-radius:22px;padding:26px 24px 20px;text-align:center;' +
        'box-shadow:0 30px 70px -20px rgba(30,34,110,.55);transform:translateY(14px) scale(.96);transition:transform .3s cubic-bezier(.2,.9,.3,1.25)}' +
        '.mmui-ov.in .mmui-dlg{transform:none}' +
        '.mmui-dic{width:56px;height:56px;border-radius:18px;margin:0 auto 14px;display:grid;place-items:center;font-size:24px;color:#fff;background:var(--mmui-grad)}' +
        '.mmui-dlg.danger .mmui-dic{background:linear-gradient(135deg,#f08a9b,#d6455d)}' +
        '.mmui-dlg h3{margin:0 0 8px;font-size:18px;font-weight:800}.mmui-dlg p{margin:0 0 22px;font-size:14px;line-height:1.55;color:var(--mmui-muted)}' +
        '.mmui-btns{display:flex;gap:10px}.mmui-btns button{flex:1;border:0;border-radius:13px;padding:12px 14px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;transition:transform .15s,filter .15s}' +
        '.mmui-btns button:active{transform:scale(.97)}' +
        '.mmui-no{background:var(--mmui-line);color:var(--mmui-ink)}.mmui-ok{background:var(--mmui-grad);color:#fff;box-shadow:0 10px 22px -10px rgba(91,95,230,.8)}' +
        '.mmui-dlg.danger .mmui-ok{background:linear-gradient(135deg,#f08a9b,#d6455d);box-shadow:0 10px 22px -10px rgba(214,69,93,.8)}' +
        '.mmui-btns button:hover{filter:brightness(1.06)}.mmui-btns button:focus-visible{outline:3px solid rgba(91,95,230,.4);outline-offset:2px}' +
        '@media (prefers-reduced-motion:reduce){.mmui-toast,.mmui-dlg,.mmui-ov{transition:none}.mmui-bar{animation:none}}';

    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    var ICONS = { success: '✓', error: '!', warning: '!', info: 'i' };
    var TITLES = { success: 'Done', error: 'Something went wrong', warning: 'Heads up', info: 'Notice' };
    var host;

    function getHost() {
        if (!host || !document.body.contains(host)) {
            host = document.createElement('div');
            host.id = 'mmui-toasts';
            host.className = 'mmui';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        return host;
    }

    function toast(message, type, opts) {
        type = ICONS[type] ? type : 'info';
        opts = opts || {};
        var ms = opts.duration || (type === 'error' ? 6500 : 4200);

        function build() {
            var el = document.createElement('div');
            el.className = 'mmui-toast mmui-' + type;
            el.setAttribute('role', type === 'error' ? 'alert' : 'status');

            var ic = document.createElement('div'); ic.className = 'mmui-ic'; ic.textContent = ICONS[type];
            var tx = document.createElement('div'); tx.className = 'mmui-tx';
            var tt = document.createElement('div'); tt.className = 'mmui-tt'; tt.textContent = opts.title || TITLES[type];
            var ms_ = document.createElement('div'); ms_.className = 'mmui-ms'; ms_.textContent = String(message == null ? '' : message);
            var x = document.createElement('button'); x.className = 'mmui-x'; x.setAttribute('aria-label', 'Dismiss'); x.textContent = '×';
            var bar = document.createElement('div'); bar.className = 'mmui-bar'; bar.style.animationDuration = ms + 'ms';

            tx.appendChild(tt); tx.appendChild(ms_);
            el.appendChild(ic); el.appendChild(tx); el.appendChild(x); el.appendChild(bar);

            var timer;
            function close() {
                clearTimeout(timer);
                el.classList.remove('in'); el.classList.add('out');
                setTimeout(function () { el.remove(); }, 320);
            }
            x.addEventListener('click', close);
            el.addEventListener('mouseenter', function () { clearTimeout(timer); bar.style.animationPlayState = 'paused'; });
            el.addEventListener('mouseleave', function () { bar.style.animationPlayState = 'running'; timer = setTimeout(close, 1800); });
            timer = setTimeout(close, ms);

            var h = getHost();
            h.appendChild(el);
            while (h.children.length > 4) h.firstChild.remove();
            requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.add('in'); }); });
        }

        if (document.body) build(); else document.addEventListener('DOMContentLoaded', build);
    }

    function confirmDialog(message, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var ov = document.createElement('div');
            ov.className = 'mmui mmui-ov';
            ov.innerHTML =
                '<div class="mmui-dlg' + (opts.danger ? ' danger' : '') + '" role="alertdialog" aria-modal="true">' +
                '<div class="mmui-dic">' + (opts.danger ? '!' : '?') + '</div>' +
                '<h3></h3><p></p>' +
                '<div class="mmui-btns"><button type="button" class="mmui-no"></button><button type="button" class="mmui-ok"></button></div></div>';
            ov.querySelector('h3').textContent = opts.title || 'Please confirm';
            ov.querySelector('p').textContent = String(message || '');
            var no = ov.querySelector('.mmui-no'), ok = ov.querySelector('.mmui-ok');
            no.textContent = opts.cancelText || 'Cancel';
            ok.textContent = opts.okText || 'Confirm';

            var prev = document.activeElement;
            function done(v) {
                document.removeEventListener('keydown', onKey, true);
                ov.classList.remove('in');
                setTimeout(function () { ov.remove(); if (prev && prev.focus) { try { prev.focus(); } catch (e) {} } }, 220);
                resolve(v);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); done(false); }
                if (e.key === 'Tab') { e.preventDefault(); (document.activeElement === ok ? no : ok).focus(); }
            }
            no.addEventListener('click', function () { done(false); });
            ok.addEventListener('click', function () { done(true); });
            ov.addEventListener('mousedown', function (e) { if (e.target === ov) done(false); });
            document.addEventListener('keydown', onKey, true);

            document.body.appendChild(ov);
            requestAnimationFrame(function () { requestAnimationFrame(function () { ov.classList.add('in'); ok.focus(); }); });
        });
    }

    window.MM = { toast: toast, confirm: confirmDialog };

    // Old alert() calls become non-blocking toasts. Errors are detected from the wording.
    window.alert = function (msg) {
        var text = String(msg == null ? '' : msg);
        var bad = /(error|fail|could not|couldn't|cannot|can't|unable|denied|expired|invalid|wrong|not found)/i.test(text);
        toast(text, bad ? 'error' : 'info');
    };
})();
