/**
 * LumiCode — topbar dark/light toggle
 *
 * SYNC RULES:
 *   1. Topbar button ←→ Features "Light Mode" checkbox (bidirectional, instant)
 *   2. Both save immediately to DB via AJAX (no Save button needed)
 *   3. Frontend picks up new setting on NEXT page load (DB is source of truth)
 *   4. When topbar is clicked: show a friendly modal asking whether to also
 *      apply to the frontend. "Yes" saves to DB. "Admin only" still saves
 *      but the DB value is what the frontend reads on next load anyway,
 *      so the distinction is: YES = saves, NO = does not save (admin-only visual toggle).
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var wrap    = document.getElementById('lc-wrap');
        var btn     = document.getElementById('lc-mode-toggle');
        var icon    = document.getElementById('lc-mode-icon');
        var label   = document.getElementById('lc-mode-label');
        if (!wrap || !btn || !icon || !label) {
            return;
        }

        var isLight = !!(window.LumiCodeTopbar && window.LumiCodeTopbar.isLight);

        /* Apply visual change to the admin UI */
        function applyVisual(light) {
            isLight = !!light;
            wrap.classList.toggle('lc-theme-light', isLight);
            icon.className    = isLight ? 'ph ph-sun'  : 'ph ph-moon';
            label.textContent = isLight ? 'Dark' : 'Light';
            /* Sync the Features page Light Mode checkbox */
            var cb  = document.getElementById('lc-lightmode-cb');
            var row = document.getElementById('lc-lightmode-toggle-row');
            if (cb)  { cb.checked = isLight; }
            if (row) { row.classList.toggle('is-on', isLight); }
        }

        /* Save to DB via AJAX — also updates frontend on next page load */
        function saveToDb(light, callback) {
            var cfg   = window.LumiCodeAdmin || {};
            var url   = cfg.ajaxUrl || cfg.ajax_url || '';
            var nonce = cfg.lightModeNonce || '';
            if (!url) { if (callback) callback(); return; }
            var fd = new FormData();
            fd.append('action', 'lumicode_set_light_mode');
            fd.append('nonce',  nonce);
            fd.append('light',  light ? '1' : '0');
            fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function() { if (callback) callback(); })
                .catch(function() { if (callback) callback(); });
        }

        /* Show frontend light mode modal */
        function showFrontendModal(light) {
            var modal = document.getElementById('lc-frontend-modal');
            if (!modal) {
                /* Modal not on scanner page — just save directly */
                saveToDb(light);
                return;
            }
            var iconEl = document.getElementById('lc-fmodal-icon');
            var titleEl = document.getElementById('lc-fmodal-title');
            var bodyEl  = document.getElementById('lc-fmodal-body');
            iconEl.textContent  = light ? '☀️' : '🌙';
            titleEl.textContent = light ? 'Enable light mode everywhere?' : 'Switch to dark mode everywhere?';
            bodyEl.textContent  = light
                ? 'Apply light mode to frontend code boxes too? They\'ll switch on the next page load.'
                : 'Apply dark mode to frontend code boxes too? They\'ll switch on the next page load.';
            modal.style.display = 'flex';
            requestAnimationFrame(function() {
                requestAnimationFrame(function() { modal.style.opacity = '1'; });
            });

            var yes = document.getElementById('lc-fmodal-yes');
            var no  = document.getElementById('lc-fmodal-no');
            function closeModal() {
                modal.style.opacity = '0';
                setTimeout(function() { modal.style.display = 'none'; }, 200);
            }
            yes.onclick = function() { closeModal(); saveToDb(light); };
            no.onclick  = function() { closeModal(); /* admin-only, no DB save */ };
        }

        /* Expose for Features checkbox handler (admin-settings.js) */
        window.lcApplyMode = function(light) {
            applyVisual(light);
            showFrontendModal(light);
        };

        /* Apply on page load */
        applyVisual(isLight);

        /* Topbar button click */
        btn.addEventListener('click', function() {
            var newLight = !isLight;
            applyVisual(newLight);
            showFrontendModal(newLight);
        });
    });
})();
