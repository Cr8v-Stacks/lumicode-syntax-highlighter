/**
 * LumiCode TinyMCE Dialog — v1.4.6
 *
 * INSERT STRATEGY (v1.4.6):
 * Insert a raw <pre class="lumicode-pre"><code>…</code></pre> block directly
 * into TinyMCE via editor.insertContent(). TinyMCE preserves <pre> content
 * verbatim — no shortcode needed, no tab-switching, no whitespace loss.
 * The frontend lumicode.js picks up pre.lumicode-pre and wraps it in the
 * lc-pw design automatically, exactly as it does for any other code block.
 *
 * WHY NOT SHORTCODES:
 * Shortcodes require switching to the HTML textarea and back, which causes
 * TinyMCE to re-parse the content and can mangle newlines or paste inline.
 * Raw HTML avoids all of that — it's simpler and more reliable.
 *
 * Cr8v Stacks · cr8vstacks.com
 */
(function ($) {
    'use strict';

    var $dialog, $overlay, $code, $lang, $hint, currentEditor;

    var LANG_PATTERNS = [
        { lang: 'php',        re: /(<\?php|\becho\b\s+['"\$]|\bfunction\b\s+\w+\s*\(|\$\w+\s*=)/ },
        { lang: 'python',     re: /(\bdef\b\s+\w+\s*\(|^\s*import\s+\w+|\bprint\s*\()/m },
        { lang: 'typescript', re: /:\s*(string|number|boolean|any|void|never)\b|interface\s+\w+\s*\{/ },
        { lang: 'javascript', re: /\bconst\b\s+\w+\s*=|\blet\b\s+\w+|\b=>\s*[{(]|\bconsole\.log\b/ },
        { lang: 'css',        re: /[.#][\w-]+\s*\{|:\s*[\w#"']+\s*;|@media\s/ },
        { lang: 'html',       re: /<!DOCTYPE|<html[\s>]|<head[\s>]|<body[\s>]/i },
        { lang: 'sql',        re: /\bSELECT\b.+\bFROM\b|\bINSERT\s+INTO\b|\bCREATE\s+TABLE\b/i },
        { lang: 'bash',       re: /^#!\/bin\/(?:bash|sh)|^\s*(?:export|echo|grep)\s+/m },
        { lang: 'json',       re: /^\s*[{[]\s*\n?\s*"[\w-]+"/ },
        { lang: 'go',         re: /\bpackage\s+\w+|\bfunc\s+\w+\s*\(/ },
        { lang: 'rust',       re: /\bfn\s+\w+\s*\(|\blet\s+mut\b/ },
        { lang: 'java',       re: /\bpublic\s+(?:static\s+)?(?:void|class)\b/ },
        { lang: 'yaml',       re: /^[\w-]+:\s+\S/m },
        { lang: 'typescript', re: /\basync\b.+\bPromise<|\bawait\b/ },
    ];

    function detectLanguage(code) {
        if (!code || code.trim().length < 10) return '';
        for (var i = 0; i < LANG_PATTERNS.length; i++) {
            if (LANG_PATTERNS[i].re.test(code)) return LANG_PATTERNS[i].lang;
        }
        return '';
    }

    $(document).ready(function () {
        $dialog  = $('#lumicode-tmce-dialog');
        $overlay = $('#lumicode-tmce-overlay');
        $code    = $('#lumicode-tmce-code');
        $lang    = $('#lumicode-tmce-lang');
        $hint    = $('#lumicode-detect-hint');

        $overlay.on('click', closeDialog);
        $('#lumicode-tmce-close, #lumicode-tmce-cancel').on('click', closeDialog);
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $dialog.css('display') !== 'none') closeDialog();
        });

        var detectTimer;
        $code.on('input paste', function () {
            clearTimeout(detectTimer);
            detectTimer = setTimeout(function () {
                if ($lang.data('user-picked')) return;
                var detected = detectLanguage($code.val());
                if (detected) { $lang.val(detected); $hint.show(); }
                else $hint.hide();
            }, 350);
        });

        $lang.on('change', function () {
            $lang.data('user-picked', true);
            $hint.hide();
        });

        $('#lumicode-tmce-insert').on('click', insertCode);
        $dialog.on('keydown', function (e) {
            if (e.key === 'Enter' && e.ctrlKey) insertCode();
        });
    });

    /* ── Open ───────────────────────────────────────────────── */
    window.lumiCodeOpenDialog = function (editor) {
        currentEditor = editor;
        $lang.val('').removeData('user-picked');
        $('#lumicode-tmce-title').val('');
        $('#lumicode-tmce-highlight').val('');
        $('#lumicode-tmce-collapse').prop('checked', false);
        $('#lumicode-tmce-collapse-lines').val('0');
        $hint.hide();
        $code.val('').css('border-color', '');

        try {
            var sel = editor.selection.getContent({ format: 'text' }).trim();
            if (sel) {
                $code.val(sel);
                var d = detectLanguage(sel);
                if (d) { $lang.val(d); $hint.show(); }
            }
        } catch (e) {}

        $overlay.css({ display: 'block', opacity: 0 }).animate({ opacity: 1 }, 150);
        $dialog.css({ display: 'flex', opacity: 0 }).animate({ opacity: 1 }, 150);
        setTimeout(function () { $code.focus(); }, 160);
    };

    /* ── Close ──────────────────────────────────────────────── */
    function closeDialog() {
        $dialog.animate({ opacity: 0 }, 120, function () { $(this).css('display', 'none'); });
        $overlay.animate({ opacity: 0 }, 120, function () { $(this).css('display', 'none'); });
        currentEditor = null;
    }

    /* ── Insert ─────────────────────────────────────────────── */
    function insertCode() {
        var lang           = $lang.val().trim();
        var title          = $('#lumicode-tmce-title').val().trim();
        var highlight      = $('#lumicode-tmce-highlight').val().trim();
        var rawCode        = $code.val();
        var collapseForced = $('#lumicode-tmce-collapse').is(':checked');
        var collapseLines  = parseInt($('#lumicode-tmce-collapse-lines').val(), 10) || 0;

        if (!rawCode.trim()) {
            $code.css('border-color', 'rgba(248,113,113,0.6)').focus();
            setTimeout(function () { $code.css('border-color', ''); }, 1500);
            return;
        }

        if (!currentEditor) { closeDialog(); return; }

        /*
         * BUILD THE HTML BLOCK
         * We insert a raw <pre class="lumicode-pre"> block into TinyMCE.
         * TinyMCE preserves <pre> content verbatim in visual mode.
         * The frontend lumicode.js will pick up pre.lumicode-pre and apply the design.
         * No shortcode, no tab switching, no whitespace loss.
         */
        var dataAttrs = ' class="lumicode-pre"';
        if (lang)            dataAttrs += ' data-lang="'           + escAttr(lang)      + '"';
        if (title)           dataAttrs += ' data-title="'          + escAttr(title)     + '"';
        if (highlight)       dataAttrs += ' data-highlight="'      + escAttr(highlight) + '"';
        if (collapseForced)  dataAttrs += ' data-collapse="true"';
        if (collapseLines > 0) dataAttrs += ' data-collapse-after="' + collapseLines    + '"';

        var codeClass = lang ? ' class="language-' + escAttr(lang) + '"' : '';

        // Escape HTML special chars in the code so the browser renders it as text
        var escapedCode = rawCode
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        var html = '<pre' + dataAttrs + '><code' + codeClass + '>' + escapedCode + '</code></pre>';

        // Insert directly into TinyMCE visual editor — no tab switch needed.
        // TinyMCE preserves <pre> innerHTML as-is (no wpautop, no mangling).
        currentEditor.insertContent(html);

        closeDialog();
    }

    function escAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

})(jQuery);
