<?php
/**
 * LumiCode TinyMCE Integration — v1.4.6
 * Inserts raw <pre class="lumicode-pre"> HTML into the editor.
 * Dialog markup is printed in the editor footer; CSS and JS are enqueued.
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
        wp_enqueue_style(
            'lumicode-tmce-dialog-css',
            LUMICODE_URL . 'assets/css/tinymce-dialog.css',
            [], LUMICODE_VERSION
        );
        wp_enqueue_script(
            'lumicode-tmce-dialog',
            LUMICODE_URL . 'assets/js/tinymce-insert.js',
            [ 'jquery' ], LUMICODE_VERSION, true
        );
        wp_add_inline_script( 'lumicode-tmce-dialog',
            'window.LumiCodeTMCE = ' . wp_json_encode( [
                'i18n' => [
                    'insertTitle' => __( 'Insert LumiCode Block', 'lumicode-syntax-highlighter' ),
                    'insertText'  => __( '⚡ Code', 'lumicode-syntax-highlighter' ),
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

        <div id="lumicode-tmce-overlay"></div>
        <div id="lumicode-tmce-dialog">

            <div id="lc-dlg-header">
                <div id="lc-dlg-brand">
                    <div id="lc-dlg-icon">
                        <svg width="14" height="14" viewBox="0 0 256 256" fill="currentColor">
                            <path d="M213.85,125.46l-112,120a8,8,0,0,1-13.69-7l14.66-73.34L56.14,130.54a8,8,0,0,1,1-13.08l112-120a8,8,0,0,1,13.69,7L168.17,117.88l46.68,34.58A8,8,0,0,1,213.85,125.46Z"/>
                        </svg>
                    </div>
                    <span><?php esc_html_e( 'Insert Code Block', 'lumicode-syntax-highlighter' ); ?></span>
                </div>
                <button id="lumicode-tmce-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'lumicode-syntax-highlighter' ); ?>">&times;</button>
            </div>

            <div id="lc-dlg-body">

                <!-- Row 1: Language + Filename -->
                <div class="lc-dlg-row2">
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-lang"><?php esc_html_e( 'Language', 'lumicode-syntax-highlighter' ); ?></label>
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
                        <span id="lumicode-detect-hint">⚡ <?php esc_html_e( 'Auto-detected', 'lumicode-syntax-highlighter' ); ?></span>
                    </div>
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-title"><?php esc_html_e( 'Filename', 'lumicode-syntax-highlighter' ); ?> <span class="opt"><?php esc_html_e( 'optional', 'lumicode-syntax-highlighter' ); ?></span></label>
                        <input type="text" id="lumicode-tmce-title" placeholder="<?php esc_attr_e( 'e.g. config.php', 'lumicode-syntax-highlighter' ); ?>">
                    </div>
                </div>

                <!-- Code textarea -->
                <div class="lc-dlg-field">
                    <label for="lumicode-tmce-code"><?php esc_html_e( 'Code', 'lumicode-syntax-highlighter' ); ?></label>
                    <textarea id="lumicode-tmce-code" rows="10"
                        placeholder="<?php esc_attr_e( 'Paste or type your code here…', 'lumicode-syntax-highlighter' ); ?>"
                        spellcheck="false"></textarea>
                </div>

                <!-- Row 3: Highlight lines + Collapse after + Force collapse -->
                <div class="lc-dlg-row3">
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-highlight">
                            <?php esc_html_e( 'Highlight Lines', 'lumicode-syntax-highlighter' ); ?>
                            <span class="lc-dlg-tip" tabindex="0">?
                                <span class="lc-dlg-tip-box">
                                    <?php esc_html_e( 'Accent specific lines with a subtle highlight.', 'lumicode-syntax-highlighter' ); ?><br><br>
                                    <strong><?php esc_html_e( 'Examples:', 'lumicode-syntax-highlighter' ); ?></strong><br>
                                    <code>3</code> — <?php esc_html_e( 'single line', 'lumicode-syntax-highlighter' ); ?><br>
                                    <code>1,5</code> — <?php esc_html_e( 'lines 1 and 5', 'lumicode-syntax-highlighter' ); ?><br>
                                    <code>3-7</code> — <?php esc_html_e( 'range', 'lumicode-syntax-highlighter' ); ?><br>
                                    <code>1,3-5,9</code> — <?php esc_html_e( 'mixed', 'lumicode-syntax-highlighter' ); ?><br><br>
                                    <?php esc_html_e( 'Lines are numbered from 1.', 'lumicode-syntax-highlighter' ); ?>
                                </span>
                            </span>
                        </label>
                        <input type="text" id="lumicode-tmce-highlight" placeholder="<?php esc_attr_e( 'e.g. 1,3-5', 'lumicode-syntax-highlighter' ); ?>">
                    </div>
                    <div class="lc-dlg-field">
                        <label for="lumicode-tmce-collapse-lines">
                            <?php esc_html_e( 'Collapse After', 'lumicode-syntax-highlighter' ); ?> <span class="opt"><?php esc_html_e( 'lines', 'lumicode-syntax-highlighter' ); ?></span>
                        </label>
                        <input type="number" id="lumicode-tmce-collapse-lines"
                            placeholder="<?php esc_attr_e( 'e.g. 30', 'lumicode-syntax-highlighter' ); ?>" min="0" max="500" value="0">
                    </div>
                    <div class="lc-dlg-field">
                        <label>&nbsp;</label>
                        <label class="lc-dlg-check">
                            <input type="checkbox" id="lumicode-tmce-collapse">
                            <span><?php esc_html_e( 'Force collapse', 'lumicode-syntax-highlighter' ); ?></span>
                        </label>
                    </div>
                </div>

            </div><!-- /body -->

            <div id="lc-dlg-footer">
                <button id="lumicode-tmce-cancel" type="button"><?php esc_html_e( 'Cancel', 'lumicode-syntax-highlighter' ); ?></button>
                <button id="lumicode-tmce-insert" type="button">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <?php esc_html_e( 'Insert Code Block', 'lumicode-syntax-highlighter' ); ?>
                </button>
            </div>

        </div>


        <?php
    }

    public static function language_options() {
        return [
            ''  => __( 'Auto-detect', 'lumicode-syntax-highlighter' ),
            // Web
            __( 'Web', 'lumicode-syntax-highlighter' ) => [
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
            __( 'Backend & Systems', 'lumicode-syntax-highlighter' ) => [
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
            __( 'Data & Query', 'lumicode-syntax-highlighter' ) => [
                'sql'        => 'SQL',
                'mysql'      => 'MySQL',
                'pgsql'      => 'PostgreSQL',
                'mongodb'    => 'MongoDB',
                'graphql'    => 'GraphQL',
            ],
            // Shell & Config
            __( 'Shell & Config', 'lumicode-syntax-highlighter' ) => [
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
            __( 'Markup & Docs', 'lumicode-syntax-highlighter' ) => [
                'markdown'   => 'Markdown',
                'latex'      => 'LaTeX',
                'asciidoc'   => 'AsciiDoc',
            ],
            // Other
            __( 'Other', 'lumicode-syntax-highlighter' ) => [
                'diff'       => 'Diff / Patch',
                'makefile'   => 'Makefile',
                'vim'        => 'Vim Script',
                'nix'        => 'Nix',
                'solidity'   => 'Solidity',
                'wasm'       => 'WebAssembly',
                'plaintext'  => __( 'Plain Text', 'lumicode-syntax-highlighter' ),
            ],
        ];
    }
}
