<?php
/**
 * LumiCode TinyMCE Integration — v1.4.6
 * Inserts raw <pre class="lumicode-pre"> HTML into the editor.
 * Dialog CSS is output inline in admin_footer to guarantee it loads.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_TinyMCE {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'setup_hooks' ], 20 );
    }

    public static function setup_hooks() {
        if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) return;
        add_filter( 'mce_buttons',           [ __CLASS__, 'register_button' ] );
        add_filter( 'mce_external_plugins',  [ __CLASS__, 'register_plugin' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_dialog' ] );
        add_action( 'admin_footer',          [ __CLASS__, 'render_dialog' ] );
    }

    public static function register_button( $buttons ) {
        array_push( $buttons, 'separator', 'lumicode_insert' );
        return $buttons;
    }

    public static function register_plugin( $plugins ) {
        $plugins['lumicode_insert'] = LUMICODE_URL . 'assets/js/tinymce-plugin.js';
        return $plugins;
    }

    public static function enqueue_dialog( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        wp_enqueue_script(
            'lumicode-tmce-dialog',
            LUMICODE_URL . 'assets/js/tinymce-insert.js',
            [ 'jquery' ], LUMICODE_VERSION, true
        );
        wp_add_inline_script( 'lumicode-tmce-dialog',
            'window.LumiCodeTMCE = ' . wp_json_encode( [
                'i18n' => [
                    'insertTitle' => __( 'Insert LumiCode Block', 'lumicode' ),
                    'insertText'  => __( '⚡ Code', 'lumicode' ),
                ]
            ] ) . ';',
            'before'
        );
    }

    public static function render_dialog() {
        if ( ! function_exists( 'get_current_screen' ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->base, [ 'post', 'page' ], true ) ) return;

        $langs = self::language_options();
        ?>
        <style id="lumicode-dialog-css">
        #lumicode-tmce-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.72); z-index: 100000;
            backdrop-filter: blur(3px);
        }
        #lumicode-tmce-dialog {
            display: none; position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 100001;
            width: 600px; max-width: calc(100vw - 32px);
            max-height: 92vh;
            flex-direction: column;
            background: #0f1117;
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 14px;
            box-shadow: 0 32px 96px rgba(0,0,0,0.75), 0 0 0 1px rgba(255,255,255,0.04);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #e2e8f0; overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Header */
        #lc-dlg-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 18px; height: 54px; flex-shrink: 0;
            background: rgba(0,0,0,0.32);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        #lc-dlg-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 700; color: #e2e8f0;
        }
        #lc-dlg-icon {
            width: 28px; height: 28px; border-radius: 7px;
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; color: #09090f;
        }
        #lumicode-tmce-close {
            width: 30px; height: 30px; border-radius: 7px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.10);
            color: rgba(148,163,184,0.7);
            font-size: 18px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        #lumicode-tmce-close:hover { background: rgba(255,255,255,0.13); color: #e2e8f0; }

        /* Body */
        #lc-dlg-body {
            padding: 18px; overflow-y: auto; flex: 1;
            display: flex; flex-direction: column; gap: 14px;
        }
        .lc-dlg-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .lc-dlg-row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .lc-dlg-field { display: flex; flex-direction: column; gap: 5px; }

        .lc-dlg-field label {
            display: flex; align-items: center; gap: 0;
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: rgba(148,163,184,0.55);
        }
        .lc-dlg-field label .opt {
            font-weight: 400; text-transform: none; letter-spacing: 0;
            color: rgba(148,163,184,0.32); font-size: 10px; margin-left: 5px;
        }

        /* All text inputs and selects share identical styling */
        .lc-dlg-field select,
        .lc-dlg-field input[type="text"],
        .lc-dlg-field input[type="number"] {
            width: 100%; padding: 9px 12px;
            background: rgba(0,0,0,0.45);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 7px; color: #e2e8f0;
            font-size: 13px; outline: none; box-shadow: none;
            -webkit-appearance: none; appearance: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .lc-dlg-field input[type="number"] {
            -moz-appearance: textfield;
        }
        .lc-dlg-field input[type="number"]::-webkit-inner-spin-button,
        .lc-dlg-field input[type="number"]::-webkit-outer-spin-button {
            opacity: 0.4;
        }
        .lc-dlg-field select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='rgba(148,163,184,0.4)' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center;
            padding-right: 30px; cursor: pointer;
        }
        .lc-dlg-field select option { background: #1a1d2e; color: #e2e8f0; }
        .lc-dlg-field select optgroup { background: #1a1d2e; color: rgba(148,163,184,0.5); font-style: normal; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
        .lc-dlg-field select:focus,
        .lc-dlg-field input:focus {
            border-color: rgba(167,139,250,0.55);
            box-shadow: 0 0 0 3px rgba(167,139,250,0.09);
            background: rgba(167,139,250,0.04);
        }

        /* Detect hint */
        #lumicode-detect-hint {
            font-size: 11px; color: rgba(167,139,250,0.75); display: none;
        }

        /* Code textarea */
        #lumicode-tmce-code {
            width: 100%; min-height: 220px; padding: 12px 14px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 8px; color: #cdd6f4;
            font-family: 'JetBrains Mono','Menlo','Monaco','Consolas',ui-monospace,monospace;
            font-size: 12.5px; line-height: 1.7;
            resize: vertical; outline: none; tab-size: 4;
            white-space: pre; overflow-x: auto; overflow-y: auto;
            word-wrap: normal;
        }
        #lumicode-tmce-code:focus { border-color: rgba(167,139,250,0.5); }

        /* Tooltip on Highlight Lines label */
        .lc-dlg-tip {
            display: inline-flex; align-items: center; justify-content: center;
            width: 14px; height: 14px; border-radius: 50%;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.18);
            color: rgba(148,163,184,0.55);
            font-size: 9px; font-weight: 700;
            cursor: help; margin-left: 6px;
            position: relative; flex-shrink: 0;
            text-transform: none; letter-spacing: 0;
        }
        .lc-dlg-tip:hover .lc-dlg-tip-box,
        .lc-dlg-tip:focus .lc-dlg-tip-box { display: block; }
        .lc-dlg-tip-box {
            display: none;
            position: absolute;
            left: 50%; bottom: calc(100% + 8px); /* ← ABOVE the icon */
            transform: translateX(-50%);
            background: #1a1d2e;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px; padding: 12px 14px;
            font-size: 12px; line-height: 1.65;
            color: rgba(148,163,184,0.85);
            width: 230px; z-index: 100002;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.5);
            text-align: left; pointer-events: none;
            white-space: normal; text-transform: none; letter-spacing: 0; font-weight: 400;
        }
        .lc-dlg-tip-box code {
            font-family: ui-monospace, monospace; font-size: 11px;
            color: #a78bfa; background: rgba(167,139,250,0.1);
            padding: 1px 5px; border-radius: 3px;
        }
        .lc-dlg-tip-box strong { color: rgba(226,232,240,0.75); }

        /* Checkbox row */
        .lc-dlg-check {
            display: flex; align-items: center; gap: 9px; cursor: pointer;
            font-size: 13px; color: rgba(148,163,184,0.65);
            text-transform: none; letter-spacing: 0; font-weight: 400;
            padding: 8px 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 7px;
            margin-top: 2px;
        }
        .lc-dlg-check:hover {
            background: rgba(255,255,255,0.06);
        }
        .lc-dlg-check input[type="checkbox"] {
            width: 15px; height: 15px; accent-color: #a78bfa;
            cursor: pointer; flex-shrink: 0;
        }

        /* Footer */
        #lc-dlg-footer {
            display: flex; justify-content: flex-end; align-items: center;
            gap: 10px; padding: 14px 18px; flex-shrink: 0;
            border-top: 1px solid rgba(255,255,255,0.07);
            background: rgba(0,0,0,0.25);
        }
        #lc-dlg-footer button {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            transition: opacity 0.15s; line-height: 1;
        }
        #lumicode-tmce-insert {
            background: linear-gradient(135deg, #a78bfa, #60a5fa);
            color: #09090f; border: none;
        }
        #lumicode-tmce-insert:hover { opacity: 0.88; }
        #lumicode-tmce-cancel {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            color: rgba(226,232,240,0.65);
        }
        #lumicode-tmce-cancel:hover { background: rgba(255,255,255,0.12); color: #e2e8f0; }

        /* ── Light mode support ─────────────────────────────────── */
        /*
         * When WP admin has #lc-wrap.lc-theme-light (or localStorage says light),
         * we apply .lc-dlg-light to the dialog + overlay for a consistent look.
         */
        #lumicode-tmce-dialog.lc-dlg-light {
            background: #f4f5f9;
            border-color: rgba(0,0,0,0.10);
            color: #1e1e2e;
            box-shadow: 0 32px 96px rgba(0,0,0,0.18), 0 0 0 1px rgba(0,0,0,0.07);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lc-dlg-header {
            background: #fff;
            border-bottom-color: rgba(0,0,0,0.08);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lc-dlg-brand { color: #1e1e2e; }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-tmce-close {
            background: rgba(0,0,0,0.06);
            border-color: rgba(0,0,0,0.10);
            color: rgba(30,30,46,0.55);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-tmce-close:hover {
            background: rgba(0,0,0,0.10);
            color: #1e1e2e;
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-field label {
            color: rgba(30,30,46,0.50);
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-field select,
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-field input[type="text"],
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-field input[type="number"] {
            background: #ffffff;
            border-color: rgba(0,0,0,0.14);
            color: #1e1e2e;
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-field select:focus,
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-field input:focus {
            border-color: rgba(124,58,237,0.45);
            box-shadow: 0 0 0 3px rgba(124,58,237,0.08);
            background: rgba(124,58,237,0.02);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-detect-hint { color: rgba(124,58,237,0.75); }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-tmce-code {
            background: #fff;
            border-color: rgba(0,0,0,0.12);
            color: #1e1e2e;
        }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-tmce-code:focus {
            border-color: rgba(124,58,237,0.45);
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-check {
            background: rgba(0,0,0,0.03);
            border-color: rgba(0,0,0,0.08);
            color: rgba(30,30,46,0.6);
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-check:hover {
            background: rgba(0,0,0,0.06);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lc-dlg-footer {
            background: #fff;
            border-top-color: rgba(0,0,0,0.07);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-tmce-cancel {
            background: rgba(0,0,0,0.05);
            border-color: rgba(0,0,0,0.12);
            color: rgba(30,30,46,0.6);
        }
        #lumicode-tmce-dialog.lc-dlg-light #lumicode-tmce-cancel:hover {
            background: rgba(0,0,0,0.09);
            color: #1e1e2e;
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-tip {
            background: rgba(0,0,0,0.08);
            border-color: rgba(0,0,0,0.14);
            color: rgba(30,30,46,0.5);
        }
        #lumicode-tmce-dialog.lc-dlg-light .lc-dlg-tip-box {
            background: #fff;
            border-color: rgba(0,0,0,0.10);
            color: rgba(30,30,46,0.75);
        }
        </style>

        <div id="lumicode-tmce-overlay"></div>
        <div id="lumicode-tmce-dialog">

            <div id="lc-dlg-header">
                <div id="lc-dlg-brand">
                    <div id="lc-dlg-icon">
                        <svg width="14" height="14" viewBox="0 0 256 256" fill="currentColor">
                            <path d="M213.85,125.46l-112,120a8,8,0,0,1-13.69-7l14.66-73.34L56.14,130.54a8,8,0,0,1,1-13.08l112-120a8,8,0,0,1,13.69,7L168.17,117.88l46.68,34.58A8,8,0,0,1,213.85,125.46Z"/>
                        </svg>
                    </div>
                    <span><?php _e( 'Insert Code Block', 'lumicode' ); ?></span>
                </div>
                <button id="lumicode-tmce-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'lumicode' ); ?>">&times;</button>
            </div>

            <div id="lc-dlg-body">

                <!-- Row 1: Language + Filename -->
                <div class="lc-dlg-row2">
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-lang"><?php _e( 'Language', 'lumicode' ); ?></label>
                        <select id="lumicode-tmce-lang">
                            <?php foreach ( $langs as $group => $options ) : ?>
                                <?php if ( is_array( $options ) ) : ?>
                                    <optgroup label="<?php echo esc_attr( $group ); ?>">
                                        <?php foreach ( $options as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php else : ?>
                                    <option value="<?php echo esc_attr($group); ?>"><?php echo esc_html($options); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <span id="lumicode-detect-hint">⚡ <?php _e( 'Auto-detected', 'lumicode' ); ?></span>
                    </div>
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-title"><?php _e( 'Filename', 'lumicode' ); ?> <span class="opt"><?php _e( 'optional', 'lumicode' ); ?></span></label>
                        <input type="text" id="lumicode-tmce-title" placeholder="<?php esc_attr_e( 'e.g. config.php', 'lumicode' ); ?>">
                    </div>
                </div>

                <!-- Code textarea -->
                <div class="lc-dlg-field">
                    <label for="lumicode-tmce-code"><?php _e( 'Code', 'lumicode' ); ?></label>
                    <textarea id="lumicode-tmce-code" rows="10"
                        placeholder="<?php esc_attr_e( 'Paste or type your code here…', 'lumicode' ); ?>"
                        spellcheck="false"></textarea>
                </div>

                <!-- Row 3: Highlight lines + Collapse after + Force collapse -->
                <div class="lc-dlg-row3">
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-highlight">
                            <?php _e( 'Highlight Lines', 'lumicode' ); ?>
                            <span class="lc-dlg-tip" tabindex="0">?
                                <span class="lc-dlg-tip-box">
                                    <?php _e( 'Accent specific lines with a subtle highlight.', 'lumicode' ); ?><br><br>
                                    <strong><?php _e( 'Examples:', 'lumicode' ); ?></strong><br>
                                    <code>3</code> — <?php _e( 'single line', 'lumicode' ); ?><br>
                                    <code>1,5</code> — <?php _e( 'lines 1 and 5', 'lumicode' ); ?><br>
                                    <code>3-7</code> — <?php _e( 'range', 'lumicode' ); ?><br>
                                    <code>1,3-5,9</code> — <?php _e( 'mixed', 'lumicode' ); ?><br><br>
                                    <?php _e( 'Lines are numbered from 1.', 'lumicode' ); ?>
                                </span>
                            </span>
                        </label>
                        <input type="text" id="lumicode-tmce-highlight" placeholder="<?php esc_attr_e( 'e.g. 1,3-5', 'lumicode' ); ?>">
                    </div>
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-collapse-lines">
                            <?php _e( 'Collapse After', 'lumicode' ); ?> <span class="opt"><?php _e( 'lines', 'lumicode' ); ?></span>
                        </label>
                        <input type="number" id="lumicode-tmce-collapse-lines"
                            placeholder="<?php esc_attr_e( 'e.g. 30', 'lumicode' ); ?>" min="0" max="500" value="0">
                    </div>
                    <div class="lc-dlg-field">
                        <label>&nbsp;</label>
                        <label class="lc-dlg-check">
                            <input type="checkbox" id="lumicode-tmce-collapse">
                            <span><?php _e( 'Force collapse', 'lumicode' ); ?></span>
                        </label>
                    </div>
                </div>

            </div><!-- /body -->

            <div id="lc-dlg-footer">
                <button id="lumicode-tmce-cancel" type="button"><?php _e( 'Cancel', 'lumicode' ); ?></button>
                <button id="lumicode-tmce-insert" type="button">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php _e( 'Insert Code Block', 'lumicode' ); ?>
                </button>
            </div>

        </div>

        <script>
        /* Sync TinyMCE dialog light/dark mode with admin topbar */
        (function() {
            function syncDialogMode() {
                var wrap = document.getElementById('lc-wrap');
                var dlg  = document.getElementById('lumicode-tmce-dialog');
                if (!dlg) return;
                var isLight = (wrap && wrap.classList.contains('lc-theme-light')) ||
                              localStorage.getItem('lumicode_light') === '1';
                dlg.classList.toggle('lc-dlg-light', isLight);
            }
            /* Sync on open */
            var _origOpen = window.lumiCodeOpenDialog;
            window.lumiCodeOpenDialog = function(editor) {
                syncDialogMode();
                if (_origOpen) _origOpen(editor);
            };
            /* Also sync whenever lc-wrap class changes (MutationObserver) */
            var wrap = document.getElementById('lc-wrap');
            if (wrap && window.MutationObserver) {
                new MutationObserver(syncDialogMode).observe(wrap, { attributes: true, attributeFilter: ['class'] });
            }
            /* Initial sync on DOM ready */
            document.addEventListener('DOMContentLoaded', syncDialogMode);
        })();
        </script>

        <?php
    }

    public static function language_options() {
        return [
            ''  => __( 'Auto-detect', 'lumicode' ),
            // Web
            __( 'Web', 'lumicode' ) => [
                'html'       => 'HTML',
                'css'        => 'CSS',
                'scss'       => 'SCSS',
                'less'       => 'Less',
                'javascript' => 'JavaScript',
                'typescript' => 'TypeScript',
                'jsx'        => 'JSX / React',
                'tsx'        => 'TSX',
                'json'       => 'JSON',
                'graphql'    => 'GraphQL',
                'xml'        => 'XML',
                'svg'        => 'SVG',
            ],
            // Backend / Systems
            __( 'Backend & Systems', 'lumicode' ) => [
                'php'        => 'PHP',
                'python'     => 'Python',
                'ruby'       => 'Ruby',
                'java'       => 'Java',
                'kotlin'     => 'Kotlin',
                'scala'      => 'Scala',
                'go'         => 'Go',
                'rust'       => 'Rust',
                'c'          => 'C',
                'cpp'        => 'C++',
                'csharp'     => 'C#',
                'swift'      => 'Swift',
                'objectivec' => 'Objective-C',
                'dart'       => 'Dart',
                'elixir'     => 'Elixir',
                'erlang'     => 'Erlang',
                'haskell'    => 'Haskell',
                'lua'        => 'Lua',
                'perl'       => 'Perl',
                'r'          => 'R',
                'julia'      => 'Julia',
                'clojure'    => 'Clojure',
                'ocaml'      => 'OCaml',
                'fsharp'     => 'F#',
                'vbnet'      => 'VB.NET',
            ],
            // Data & Query
            __( 'Data & Query', 'lumicode' ) => [
                'sql'        => 'SQL',
                'mysql'      => 'MySQL',
                'pgsql'      => 'PostgreSQL',
                'mongodb'    => 'MongoDB',
                'graphql'    => 'GraphQL',
            ],
            // Shell & Config
            __( 'Shell & Config', 'lumicode' ) => [
                'bash'       => 'Bash / Shell',
                'powershell' => 'PowerShell',
                'cmd'        => 'CMD / Batch',
                'yaml'       => 'YAML',
                'toml'       => 'TOML',
                'ini'        => 'INI / Config',
                'nginx'      => 'Nginx',
                'apache'     => 'Apache',
                'dockerfile' => 'Dockerfile',
                'terraform'  => 'Terraform',
                'ansible'    => 'Ansible',
            ],
            // Markup & Docs
            __( 'Markup & Docs', 'lumicode' ) => [
                'markdown'   => 'Markdown',
                'latex'      => 'LaTeX',
                'asciidoc'   => 'AsciiDoc',
            ],
            // Other
            __( 'Other', 'lumicode' ) => [
                'diff'       => 'Diff / Patch',
                'makefile'   => 'Makefile',
                'vim'        => 'Vim Script',
                'nix'        => 'Nix',
                'solidity'   => 'Solidity',
                'wasm'       => 'WebAssembly',
                'plaintext'  => __( 'Plain Text', 'lumicode' ),
            ],
        ];
    }
}
