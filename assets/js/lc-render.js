/**
 * LumiCode — Frontend Renderer v1.5.7
 * Cr8v Stacks · cr8vstacks.com
 *
 * KEY CHANGES:
 *   - Gutter min-height: after rendering lined blocks, JS reads
 *     codeArea.offsetHeight and sets gutter.style.minHeight.
 *     This fixes the gutter stopping short regardless of overflow/BFC.
 *   - Light mode modal: when topbar toggle is used, a modal asks
 *     if light mode should also apply to frontend code boxes.
 *     Frontend picks up the setting on next page load from DB.
 *   - v1.5.7: Two-pass container detection. Pass 1 finds fake chrome
 *     (headers/copy buttons) and neutralizes those containers + intermediates.
 *     Pass 2 neutralizes plain-only wrapper divs around the <pre>.
 *     lc-neutralized CSS now only strips decoration, never layout properties.
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

        document.documentElement.classList.toggle('lumicode-is-light', isLight);

        injectStyle('lc-fonts',
            'pre.lumicode-pre,pre.lumicode-pre code,.lc-pw-code pre,.lc-pw-code pre code{font-family:' + ff + '!important;font-size:' + fs + '!important}' +
            '.lumicode-inline-code,.lumicode-inline-kbd,.lumicode-inline-samp,.lumicode-inline-var{font-family:' + ff + '!important}' +
            '.lc-pw-line-numbers{font-size:' + fs + '!important;line-height:1.5!important}' +
            '.lc-pw-line-numbers span{line-height:1.5!important}'
        );

        enhanceInlineCode();

        // Clean up manual copy/paste actions from our code blocks (e.g. via Ctrl+C / right-click)
        document.addEventListener('copy', function (e) {
            var sel = window.getSelection();
            if (!sel.rangeCount) return;
            var range = sel.getRangeAt(0);
            var container = range.commonAncestorContainer;
            var isLumi = false;
            var curr = container;
            while (curr) {
                if (curr.nodeType === 1 && (curr.classList.contains('lc-pw') || curr.classList.contains('lumicode-pre') || curr.classList.contains('lc-pw-code'))) {
                    isLumi = true;
                    break;
                }
                curr = curr.parentNode;
            }
            if (isLumi) {
                var text = sel.toString();
                if (text) {
                    var cleaned = text
                        .replace(/\u00a0/g, ' ')   // non-breaking space → regular space
                        .replace(/\u200b/g, '')    // zero-width space → gone
                        .replace(/\u200c/g, '')    // zero-width non-joiner → gone
                        .replace(/\u200d/g, '')    // zero-width joiner → gone
                        .replace(/\ufeff/g, '')    // BOM → gone
                        .replace(/\r\n/g, '\n')    // Windows line endings → Unix
                        .replace(/\r/g, '\n');     // old Mac line endings → Unix
                    if (cleaned !== text) {
                        e.clipboardData.setData('text/plain', cleaned);
                        e.preventDefault();
                    }
                }
            }
        });

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
    function enhanceInlineCode() {
        markInline('code', 'lumicode-inline-code');
        markInline('kbd', 'lumicode-inline-kbd');
        markInline('samp', 'lumicode-inline-samp');
        markInline('var', 'lumicode-inline-var');
    }

    function markInline(selector, className) {
        document.querySelectorAll(selector).forEach(function (el) {
            if (el.closest('pre, .lc-pw')) return;
            el.classList.add(className);
        });
    }

    function enhanceBlock(pre) {
        if (pre.dataset.lcDone) return;
        pre.dataset.lcDone = '1';

        var code = pre.querySelector('code');
        if (!code) {
            code = document.createElement('code');
            code.textContent = pre.textContent;
            pre.innerHTML = ''; pre.appendChild(code);
        }

        // Capture raw text and immediately normalize it.
        // WordPress/Elementor saves &nbsp; for blank lines/indentation; the browser
        // decodes those to \u00a0 in textContent, which shows as invisible chars when pasted.
        // We also strip zero-width chars and normalize line endings here, once, for everything
        // that follows (highlight.js, data-lc-raw, and the copy handler).
        var rawText = (code.textContent || '')
            .replace(/\u00a0/g, ' ')   // non-breaking space → regular space
            .replace(/\u200b/g, '')    // zero-width space → gone
            .replace(/\u200c/g, '')    // zero-width non-joiner → gone
            .replace(/\u200d/g, '')    // zero-width joiner → gone
            .replace(/\ufeff/g, '')    // BOM → gone
            .replace(/\r\n/g, '\n')    // Windows line endings → Unix
            .replace(/\r/g, '\n');     // old Mac line endings → Unix
        var lang        = pre.dataset.lang || getLangFromClass(code.className) || '';
        runHljs(code, rawText, lang);
        var displayLang = lang || getLangFromClass(code.className) || '';

        // --- Pass 1: Fake-chrome detection (up to 3 ancestor levels) ---
        // Only neutralize a container if it actually contains fake chrome:
        // custom headers, titlebars, toolbars, or copy buttons that are not our own.
        // When found, also neutralize any intermediate child containers between that
        // ancestor and the <pre> (e.g. .widget-code inside .widget-block).
        (function detectFakeChrome() {
            var ancestor = pre.parentElement;
            var limit = 3;

            while (ancestor && limit > 0) {
                var hiddenHeaders = [];
                var hiddenCopy   = [];

                ancestor.querySelectorAll(
                    '[class*="header" i], [class*="titlebar" i], [class*="toolbar" i]'
                ).forEach(function (el) {
                    if (
                        el.closest('.lc-pw') ||
                        el.classList.contains('lc-pw-titlebar') ||
                        el.classList.contains('lc-pw-dots') ||
                        el.classList.contains('lc-pw-dot')
                    ) return;
                    el.style.display = 'none';
                    hiddenHeaders.push(el);
                });

                ancestor.querySelectorAll(
                    'button[class*="copy" i], button[id*="copy" i], a[class*="copy" i], [onclick*="copy" i]'
                ).forEach(function (el) {
                    if (el.closest('.lc-pw') || el.classList.contains('lc-pw-copybtn')) return;
                    el.style.display = 'none';
                    hiddenCopy.push(el);
                });

                if (hiddenHeaders.length > 0 || hiddenCopy.length > 0) {
                    ancestor.classList.add('lc-neutralized');
                    // Neutralize intermediate children that contain the pre (e.g. .widget-code)
                    Array.prototype.forEach.call(ancestor.children, function (child) {
                        if (typeof child.contains === 'function' && child.contains(pre)) {
                            child.classList.add('lc-neutralized');
                        }
                    });
                    break; // Stop — found and handled the fake-chrome container
                }

                ancestor = ancestor.parentElement;
                limit--;
            }
        }());

        // --- Pass 2: Plain-wrapper neutralization (direct parent only) ---
        // If the immediate parent of the <pre> contains nothing but the <pre> itself
        // (no other visible, non-trivial siblings), neutralize it so its background,
        // padding, and overflow don't visually envelope our code block UI.
        (function neutralizePlainWrapper() {
            var directParent = pre.parentElement;
            if (!directParent || directParent.classList.contains('lc-neutralized')) return;

            var otherChildren = Array.prototype.filter.call(directParent.children, function (child) {
                if (child === pre) return false;
                if (child.style.display === 'none') return false;
                // Treat empty/whitespace-only elements as non-significant
                if (!child.textContent.trim() && child.children.length === 0) return false;
                return true;
            });

            if (otherChildren.length === 0) {
                directParent.classList.add('lc-neutralized');
            }
        }());

        var disableChrome = pre.dataset.chrome === 'false' || pre.classList.contains('lc-no-chrome');

        if (disableChrome) {
            // Apply lines formatting if line numbers are explicitly requested or highlighting is used
            var showLineNumbers = pre.dataset.lineNumbers === 'true';
            var hasHighlight = !!pre.dataset.highlight;
            if (showLineNumbers || hasHighlight) {
                var lines = code.innerHTML.split('\n');
                if (lines[lines.length - 1] === '') lines.pop();
                code.innerHTML = lines.map(function (l) {
                    return '<span class="lc-pw-line">' + (l || '\u200b') + '</span>';
                }).join('\n');

                if (showLineNumbers) {
                    var gutter = document.createElement('div');
                    gutter.className = 'lc-pw-line-numbers';
                    gutter.setAttribute('aria-hidden', 'true');
                    for (var i = 1; i <= lines.length; i++) {
                        var s = document.createElement('span'); s.textContent = i; gutter.appendChild(s);
                    }

                    var codeArea = document.createElement('div');
                    codeArea.className = 'lc-pw-code';
                    pre.parentNode.insertBefore(codeArea, pre);
                    codeArea.appendChild(pre);

                    var lined = document.createElement('div');
                    lined.className = 'lc-pw-lined';
                    codeArea.parentNode.insertBefore(lined, codeArea);
                    lined.appendChild(gutter);
                    lined.appendChild(codeArea);

                    requestAnimationFrame(function () {
                        var h = codeArea.scrollHeight || codeArea.offsetHeight;
                        if (h > 0) gutter.style.minHeight = h + 'px';
                    });
                }
            }

            if (pre.dataset.highlight) applyLineHighlight(code, pre.dataset.highlight);
            return;
        }

        var showTitlebar = pre.dataset.titlebar !== 'false';
        var showStatusbar = pre.dataset.statusbar !== 'false';
        var showCopyButton = pre.dataset.copyButton !== 'false' && cfg.copyButton !== false;

        var codeArea = document.createElement('div');
        codeArea.className = 'lc-pw-code';
        pre.parentNode.insertBefore(codeArea, pre);
        codeArea.appendChild(pre);

        var block = document.createElement('div');
        block.className = 'lc-pw' + (isLight ? ' lc-pw-is-light' : '');
        codeArea.parentNode.insertBefore(block, codeArea);
        block.appendChild(codeArea);

        if (showTitlebar) {
            block.insertBefore(buildTitlebar(pre, code, displayLang, showCopyButton), codeArea);
        }
        if (showStatusbar) {
            block.appendChild(buildStatusBar(displayLang));
        }

        /* Line numbers FIRST — collapse wraps .lc-pw-lined or .lc-pw-code */
        var gutter = null;
        var showLineNumbers = pre.dataset.lineNumbers === 'true' || (cfg.lineNumbers && pre.dataset.lineNumbers !== 'false');
        if (showLineNumbers) gutter = insertLineNumbers(block, codeArea, pre, code);

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
        // Store the original raw text BEFORE highlight.js replaces innerHTML.
        // buildCopyBtn reads this so the clipboard always gets clean, unmodified code.
        code.dataset.lcRaw = rawText;
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
    function buildTitlebar(pre, code, lang, showCopyButton) {
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
        if (showCopyButton !== false) bar.appendChild(buildCopyBtn(code));
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
            // Prefer the original raw text stored before highlight.js ran.
            // code.textContent after highlighting contains span-join artifacts.
            var text = code.dataset.lcRaw !== undefined ? code.dataset.lcRaw : (code.textContent || '');
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
