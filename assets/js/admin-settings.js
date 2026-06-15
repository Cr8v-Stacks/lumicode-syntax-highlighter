/**
 * LumiCode — Settings Page JS v1.5.6
 * Cr8v Stacks · cr8vstacks.com
 */
(function ($) {
    'use strict';

    var cfg = window.LumiCodeAdmin || {};
    var LIGHT_THEMES = cfg.lightThemes || ['atom-one-light','github','base16/solarized-light','xcode'];

    /* ── Overlay helpers ─────────────────────────────────────── */
    /* Inject spin keyframe once */
    (function() {
        if (document.getElementById('lc-spin-style')) return;
        var st = document.createElement('style');
        st.id = 'lc-spin-style';
        st.textContent = [
            '@keyframes lc-spin{to{transform:rotate(360deg)}}',
            '#lc-frontend-modal{transition:opacity 0.2s ease}',
            '#lc-frontend-modal{opacity:0}'
        ].join('\n');
        document.head.appendChild(st);
    })();

    function showOverlay(msg) {
        var existing = document.getElementById('lc-overlay');
        if (existing) existing.remove();
        var ov = document.createElement('div');
        ov.id = 'lc-overlay';
        ov.innerHTML =
            '<div id="lc-overlay-box">' +
                '<div id="lc-overlay-spinner"></div>' +
                '<div id="lc-overlay-msg">' + msg + '</div>' +
            '</div>';
        /* Base overlay styles */
        Object.assign(ov.style, {
            position: 'fixed', inset: '0', zIndex: '99999',
            background: 'rgba(0,0,0,0.6)',
            backdropFilter: 'blur(5px)',
            WebkitBackdropFilter: 'blur(5px)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            opacity: '0', transition: 'opacity 0.2s ease'
        });
        Object.assign(ov.querySelector('#lc-overlay-box').style, {
            background: '#1c1e28',
            border: '1px solid rgba(255,255,255,0.1)',
            borderRadius: '14px', padding: '32px 44px',
            display: 'flex', flexDirection: 'column',
            alignItems: 'center', gap: '18px',
            boxShadow: '0 32px 80px rgba(0,0,0,0.55)'
        });
        Object.assign(ov.querySelector('#lc-overlay-spinner').style, {
            width: '32px', height: '32px', borderRadius: '50%',
            border: '3px solid rgba(167,139,250,0.2)',
            borderTopColor: '#a78bfa',
            animation: 'lc-spin 0.65s linear infinite'
        });
        Object.assign(ov.querySelector('#lc-overlay-msg').style, {
            fontSize: '13px', color: 'rgba(226,232,240,0.75)',
            fontFamily: 'inherit', textAlign: 'center'
        });
        document.body.appendChild(ov);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { ov.style.opacity = '1'; });
        });
        return ov;
    }

    function hideOverlay(ov, successMsg) {
        if (!ov) return;
        if (successMsg) {
            ov.querySelector('#lc-overlay-spinner').style.display = 'none';
            var msgEl = ov.querySelector('#lc-overlay-msg');
            /* Success checkmark + message */
            msgEl.innerHTML =
                '<div style="font-size:28px;margin-bottom:4px;">✓</div>' +
                '<div>' + successMsg + '</div>';
            msgEl.style.color = '#34d399';
            setTimeout(function () {
                ov.style.opacity = '0';
                setTimeout(function () { ov.remove(); }, 220);
            }, 1100);
        } else {
            ov.style.opacity = '0';
            setTimeout(function () { ov.remove(); }, 220);
        }
    }

    $(document).ready(function () {
        if (!document.getElementById('lc-settings-form')) return;

        /* Re-read cfg now that LumiCodeAdmin is available */
        cfg = window.LumiCodeAdmin || {};
        LIGHT_THEMES = cfg.lightThemes || LIGHT_THEMES;

        requestAnimationFrame(highlightPreview);
        syncToggles();
        $('#lc-settings-form').on('change', 'input[type="checkbox"]', syncToggles);

        /* ── Light Mode checkbox ─────────────────────────────── */
        /* Uses lcApplyMode (set by topbar inline script) so toggle stays in sync */
        $('#lc-lightmode-cb').on('change', function () {
            var light = this.checked;
            if (typeof window.lcApplyMode === 'function') {
                window.lcApplyMode(light);
            }
        });

        /* ── Theme selector ──────────────────────────────────── */
        $('#lc-theme').on('change', function () { applyThemePreview($(this).val()); });

        /* ── Save form ───────────────────────────────────────── */
        $('#lc-settings-form').on('submit', function () {
            /* light_mode reflects current saved state... */
            showOverlay(cfg.i18n.savingSettings || 'Saving settings…');
            /* overlay persists until WP redirects with settings-updated */
        });

        /* ── Saved confirmation ──────────────────────────────── */
        if (window.location.search.indexOf('settings-updated') !== -1) {
            var $msg = $('#lc-saved-msg');
            $msg.css('display','inline');
            setTimeout(function () { $msg.css('display','none'); }, 3500);
        }

        /* ── Flush Asset Cache ───────────────────────────────── */
        $('#lc-flush-cache').on('click', function () {
            var ov = showOverlay(cfg.i18n.clearingCache || 'Clearing cache…');
            $.post(cfg.ajaxUrl || cfg.ajax_url, {
                action: 'lumicode_flush_cache',
                nonce:  cfg.flushNonce
            })
            .done(function (res) {
                if (res && res.success) {
                    hideOverlay(ov, cfg.i18n.cacheCleared || 'Cache cleared! Reload your frontend.');
                } else {
                    hideOverlay(ov);
                    alert(cfg.i18n.flushFailedPerms || 'Cache flush failed. Check server permissions on the assets folder.');
                }
            })
            .fail(function () {
                hideOverlay(ov);
                alert(cfg.i18n.flushFailedNetwork || 'Cache flush failed. Could not reach WordPress AJAX.');
            });
        });
    });

    /* ── Toggle switch visual sync ───────────────────────────── */
    function syncToggles() {
        $('.lc-toggle').each(function () {
            var cb = $(this).find('input[type="checkbox"]')[0];
            if (cb) $(this).toggleClass('is-on', !!cb.checked);
        });
    }

    /* ── Live preview ────────────────────────────────────────── */
    function highlightPreview() {
        if (!window.hljs) return;
        var codeEl = document.querySelector('#lc-preview-code code') ||
                     document.getElementById('lc-preview-code');
        if (!codeEl) return;
        if (window.LumiCode && window.LumiCode.refreshPreview) {
            window.LumiCode.refreshPreview('typescript');
            return;
        }
        var raw = codeEl.dataset.lcRaw || codeEl.textContent;
        codeEl.dataset.lcRaw = raw;
        try {
            var r = hljs.highlight(raw, { language: 'typescript', ignoreIllegals: true });
            codeEl.innerHTML = r.value;
            codeEl.className = 'language-typescript hljs';
            codeEl.dataset.highlighted = 'yes';
        } catch (e) {}
    }

    function applyThemePreview(themeKey) {
        var isLightTheme = LIGHT_THEMES.indexOf(themeKey) !== -1;
        var localBase = cfg.localBase || LUMICODE_URL + 'assets/vendor/css/themes/';
        var newHref = localBase + themeKey + '.min.css';

        $('#lc-preview-box').css('border-color', isLightTheme ? 'rgba(0,0,0,0.12)' : '#2a2d3a');

        ['lc-theme-swap','lumicode-hljs-theme-admin-css'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.parentNode.removeChild(el);
        });

        var link = document.createElement('link');
        link.id = 'lc-theme-swap'; link.rel = 'stylesheet'; link.type = 'text/css';
        var fired = false, timer = null;
        function onReady() {
            if (fired) return; fired = true;
            if (timer) clearTimeout(timer);
            requestAnimationFrame(function () { requestAnimationFrame(highlightPreview); });
        }
        link.addEventListener('load', onReady);
        link.addEventListener('error', onReady);
        timer = setTimeout(onReady, 800);
        link.href = newHref;
        document.head.appendChild(link);
    }

})(jQuery);
