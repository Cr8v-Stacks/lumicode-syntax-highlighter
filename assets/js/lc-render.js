/**
 * LumiCode — Frontend Renderer v1.5.2
 * Cr8v Stacks · cr8vstacks.com
 *
 * KEY CHANGES vs 1.5.1:
 *   - Gutter min-height: after rendering lined blocks, JS reads
 *     codeArea.offsetHeight and sets gutter.style.minHeight.
 *     This fixes the gutter stopping short regardless of overflow/BFC.
 *   - Light mode modal: when topbar toggle is used, a modal asks
 *     if light mode should also apply to frontend code boxes.
 *     Frontend picks up the setting on next page load from DB.
 */
(function () {
    'use strict';

    var cfg      = window.LumiCode || {};
    var isLight  = !!cfg.isLight;
    var fontSize = parseFloat(cfg.fontSize) || 13;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    function boot() {
        var ff = cfg.fontFamily || "'JetBrains Mono','Fira Code',ui-monospace,monospace";
        var fs = fontSize + 'px';

        injectStyle('lc-fonts',
            '.lc-pw-code pre,.lc-pw-code pre code{font-family:' + ff + '!important;font-size:' + fs + '!important}' +
            '.lc-pw-line-numbers{font-size:' + fs + '!important;line-height:1.5!important}' +
            '.lc-pw-line-numbers span{line-height:1.5!important}'
        );

        document.querySelectorAll('pre.lumicode-pre').forEach(function (pre) {
            if (pre.closest('.lc-pw')) return;
            enhanceBlock(pre);
        });

        window.LumiCode = window.LumiCode || {};
        window.LumiCode.refreshPreview = function (lang) {
            var code = document.getElementById('lc-preview-code');
            if (!code) return;
            var raw = code.dataset.lcRaw || code.textContent;
            code.dataset.lcRaw = raw; code.className = ''; delete code.dataset.highlighted;
            runHljs(code, raw, lang || '');
            var pw = code.closest('.lc-pw'), right = pw && pw.querySelector('.lc-pw-status-right');
            if (right) { var tl = lang || getLangFromClass(code.className) || ''; right.textContent = tl ? tl[0].toUpperCase() + tl.slice(1) : ''; }
        };

        var pCode = document.getElementById('lc-preview-code');
        if (pCode) {
            pCode.dataset.lcRaw = pCode.textContent; pCode.className = '';
            runHljs(pCode, pCode.dataset.lcRaw, getLangFromClass(pCode.className) || '');
        }
    }

    /* ── Core enhancer ───────────────────────────────────────── */
    function enhanceBlock(pre) {
        if (pre.dataset.lcDone) return;
        pre.dataset.lcDone = '1';

        var code = pre.querySelector('code');
        if (!code) {
            code = document.createElement('code');
            code.textContent = pre.textContent;
            pre.innerHTML = ''; pre.appendChild(code);
        }

        var rawText     = code.textContent;
        var lang        = pre.dataset.lang || getLangFromClass(code.className) || '';
        runHljs(code, rawText, lang);
        var displayLang = lang || getLangFromClass(code.className) || '';

        var codeArea = document.createElement('div');
        codeArea.className = 'lc-pw-code';
        pre.parentNode.insertBefore(codeArea, pre);
        codeArea.appendChild(pre);

        var block = document.createElement('div');
        block.className = 'lc-pw' + (isLight ? ' lc-pw-is-light' : '');
        codeArea.parentNode.insertBefore(block, codeArea);
        block.appendChild(codeArea);

        block.insertBefore(buildTitlebar(pre, code, displayLang), codeArea);
        block.appendChild(buildStatusBar(displayLang));

        /* Line numbers FIRST — collapse wraps .lc-pw-lined or .lc-pw-code */
        var gutter = null;
        if (cfg.lineNumbers) gutter = insertLineNumbers(block, codeArea, pre, code);

        if (pre.dataset.highlight) applyLineHighlight(code, pre.dataset.highlight);

        var lineCount     = rawText.split('\n').length;
        var collapseAfter = pre.dataset.collapseAfter !== undefined
            ? parseInt(pre.dataset.collapseAfter, 10)
            : (cfg.collapseAfter || 0);
        var shouldCollapse = pre.dataset.collapse === 'true' ||
            (collapseAfter > 0 && lineCount > collapseAfter && pre.dataset.collapse !== 'false');
        if (shouldCollapse) applyCollapse(block, lineCount, collapseAfter);

        /* FIX: set gutter min-height = code column height AFTER layout.
         * offsetHeight only works after the element is in the live DOM,
         * so we use requestAnimationFrame to let the browser do one layout pass. */
        if (gutter) {
            requestAnimationFrame(function () {
                var lined = gutter.parentNode; /* .lc-pw-lined */
                if (!lined) return;
                var codeCol = lined.querySelector('.lc-pw-code');
                if (codeCol) {
                    /* Use scrollHeight so we get full content height, not clipped height */
                    var h = codeCol.scrollHeight || codeCol.offsetHeight;
                    if (h > 0) gutter.style.minHeight = h + 'px';
                }
                /* Also update after any window resize */
                window.addEventListener('resize', function () {
                    var h2 = codeCol.scrollHeight || codeCol.offsetHeight;
                    if (h2 > 0) gutter.style.minHeight = h2 + 'px';
                }, { passive: true });
            });
        }
    }

    /* ── hljs ────────────────────────────────────────────────── */
    function runHljs(code, rawText, lang) {
        if (!window.hljs) return;
        var result;
        try {
            result = (lang && hljs.getLanguage(lang))
                ? hljs.highlight(rawText, { language: lang, ignoreIllegals: true })
                : hljs.highlightAuto(rawText);
            if (!lang && result.language) lang = result.language;
        } catch (e) { code.textContent = rawText; code.classList.add('hljs'); return; }
        code.innerHTML = result.value;
        code.classList.add('hljs');
        if (lang) code.classList.add('language-' + lang);
        else if (result && result.language) code.classList.add('language-' + result.language);
        code.dataset.highlighted = 'yes';
    }

    function getLangFromClass(cls) {
        if (!cls) return '';
        var m = cls.match(/(?:language|lang)-([a-z][\w-]*)/);
        return m ? m[1] : '';
    }

    /* ── Titlebar ────────────────────────────────────────────── */
    function buildTitlebar(pre, code, lang) {
        var bar = document.createElement('div');
        bar.className = 'lc-pw-titlebar';
        var dots = document.createElement('div'); dots.className = 'lc-pw-dots'; dots.setAttribute('aria-hidden','true');
        ['red','yellow','green'].forEach(function(c) {
            var d = document.createElement('span'); d.className = 'lc-pw-dot lc-pw-dot--' + c; dots.appendChild(d);
        });
        bar.appendChild(dots);
        var ct = '', isTitle = false;
        if (pre.dataset.title) { ct = pre.dataset.title; isTitle = true; }
        else if (cfg.languageBadge !== false && lang) { ct = lang; }
        if (ct) {
            var centre = document.createElement('div'); centre.className = 'lc-pw-filename';
            if (isTitle) { var ind = document.createElement('span'); ind.className = 'lc-pw-dot-indicator'; centre.appendChild(ind); }
            var txt = document.createElement('span'); txt.textContent = ct; centre.appendChild(txt); bar.appendChild(centre);
        }
        if (cfg.copyButton !== false) bar.appendChild(buildCopyBtn(code));
        return bar;
    }

    /* ── Status bar ──────────────────────────────────────────── */
    function buildStatusBar(lang) {
        var bar = document.createElement('div'); bar.className = 'lc-pw-statusbar';
        var left = document.createElement('div'); left.className = 'lc-pw-status-left';
        ['Ln 1, Col 1','UTF-8','LF'].forEach(function(t) {
            var s = document.createElement('span'); s.textContent = t; left.appendChild(s);
        });
        bar.appendChild(left);
        if (lang) {
            var r = document.createElement('div'); r.className = 'lc-pw-status-right';
            r.textContent = lang[0].toUpperCase() + lang.slice(1); bar.appendChild(r);
        }
        return bar;
    }

    /* ── Copy button ─────────────────────────────────────────── */
    function buildCopyBtn(code) {
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'lc-pw-copybtn';
        btn.setAttribute('aria-label', cfg.i18n.copyLabel || 'Copy code');
        btn.textContent = cfg.i18n.copy || 'Copy';
        btn.addEventListener('click', function () {
            var text = code.textContent || '';
            function done() {
                btn.textContent = cfg.i18n.copied || 'Copied!';
                btn.style.color = '#28c840'; btn.style.borderColor = '#28c840';
                setTimeout(function () {
                    btn.textContent = cfg.i18n.copy || 'Copy';
                    btn.style.color = ''; btn.style.borderColor = '';
                }, 2000);
            }
            navigator.clipboard && navigator.clipboard.writeText
                ? navigator.clipboard.writeText(text).then(done).catch(function () { lcFallback(text, done); })
                : lcFallback(text, done);
        });
        return btn;
    }
    function lcFallback(text, cb) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.cssText = 'position:fixed;top:-9999px;opacity:0';
        document.body.appendChild(ta); ta.focus(); ta.select();
        try { document.execCommand('copy'); cb(); } catch(_) {}
        document.body.removeChild(ta);
    }

    /* ── COLLAPSE ────────────────────────────────────────────── */
    function applyCollapse(block, lineCount, collapseAfter) {
        var contentEl = block.querySelector('.lc-pw-lined') || block.querySelector('.lc-pw-code');
        if (!contentEl) return;

        var visLines = Math.max(5, Math.min(collapseAfter || 25, lineCount - 2));
        var clipH    = Math.round(visLines * fontSize * 1.5 + 40); /* 40 = 20px top + 20px bottom padding */

        var body = document.createElement('div');
        body.className = 'lc-pw-body is-collapsed';
        body.style.setProperty('--lc-clip-h', clipH + 'px');
        contentEl.parentNode.insertBefore(body, contentEl);
        body.appendChild(contentEl);

        var hiddenLines = lineCount - visLines;
        var overlay = document.createElement('div');
        overlay.className = 'lc-expand-overlay';

        var expandBtn = document.createElement('button');
        expandBtn.type = 'button'; expandBtn.className = 'lc-expand-btn';
        expandBtn.setAttribute('aria-expanded', 'false');
        expandBtn.innerHTML =
            '<span class="lc-expand-chevron">' + chevron('down') + '</span>' +
            '<span>' + (cfg.i18n.expand || 'Expand') + '</span>' +
            '<span class="lc-expand-count">' + hiddenLines + ' ' + (cfg.i18n.hidden || 'lines hidden') + '</span>';
        overlay.appendChild(expandBtn);
        body.appendChild(overlay);

        var collapseBar = document.createElement('div');
        collapseBar.className = 'lc-collapse-bar';
        var collapseBtn = document.createElement('button');
        collapseBtn.type = 'button'; collapseBtn.className = 'lc-collapse-btn';
        collapseBtn.innerHTML = '<span class="lc-expand-chevron">' + chevron('up') + '</span><span>' + (cfg.i18n.collapse || 'Collapse') + '</span>';
        collapseBar.appendChild(collapseBtn);

        var statusBar = block.querySelector('.lc-pw-statusbar');
        statusBar ? block.insertBefore(collapseBar, statusBar) : block.appendChild(collapseBar);

        expandBtn.addEventListener('click', function () {
            body.classList.remove('is-collapsed');
            overlay.style.display = 'none';
            collapseBar.classList.add('is-visible');
            expandBtn.setAttribute('aria-expanded', 'true');
        });
        collapseBtn.addEventListener('click', function () {
            body.classList.add('is-collapsed');
            overlay.style.display = '';
            collapseBar.classList.remove('is-visible');
            expandBtn.setAttribute('aria-expanded', 'false');
            block.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    function chevron(dir) {
        var d = dir === 'down' ? 'M3 5.5L7 9.5L11 5.5' : 'M3 8.5L7 4.5L11 8.5';
        return '<svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="' + d + '" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /* ── Line numbers — returns gutter element ───────────────── */
    function insertLineNumbers(block, codeArea, pre, code) {
        var lines = code.innerHTML.split('\n');
        if (lines[lines.length - 1] === '') lines.pop();
        code.innerHTML = lines.map(function (l) {
            return '<span class="lc-pw-line">' + (l || '\u200b') + '</span>';
        }).join('\n');

        var gutter = document.createElement('div');
        gutter.className = 'lc-pw-line-numbers';
        gutter.setAttribute('aria-hidden', 'true');
        for (var i = 1; i <= lines.length; i++) {
            var s = document.createElement('span'); s.textContent = i; gutter.appendChild(s);
        }

        var lined = document.createElement('div'); lined.className = 'lc-pw-lined';
        block.insertBefore(lined, codeArea);
        lined.appendChild(gutter);
        lined.appendChild(codeArea);

        return gutter; /* caller will set min-height after layout */
    }

    /* ── Per-line highlighting ───────────────────────────────── */
    function applyLineHighlight(code, spec) {
        var set = {};
        spec.split(',').forEach(function (part) {
            part = part.trim();
            if (part.indexOf('-') !== -1) {
                var r = part.split('-');
                for (var i = +r[0]; i <= +r[1]; i++) set[i] = true;
            } else if (part) set[+part] = true;
        });
        code.querySelectorAll('.lc-pw-line').forEach(function (el, idx) {
            if (set[idx + 1]) el.classList.add('lc-pw-line--highlighted');
        });
    }

    /* ── Utility ─────────────────────────────────────────────── */
    function injectStyle(id, css) {
        if (document.getElementById(id)) return;
        var s = document.createElement('style'); s.id = id; s.textContent = css;
        document.head.appendChild(s);
    }

})();
