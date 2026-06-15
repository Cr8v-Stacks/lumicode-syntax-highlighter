<?php
/**
 * LumiCode Admin — v1.5.6
 * Icons: bundled Phosphor Icons web font.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_ajax_lumicode_flush_cache',    [ __CLASS__, 'ajax_flush_cache' ] );
        add_action( 'wp_ajax_lumicode_set_light_mode', [ __CLASS__, 'ajax_set_light_mode' ] );
    }

    /* ── AJAX: instantly save light_mode to DB ─────────────── */
    public static function ajax_set_light_mode() {
        check_ajax_referer( 'lumicode_light_mode', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( esc_html__( 'Unauthorized', 'lumicode-syntax-highlighter' ) );
        $light = ( isset( $_POST['light'] ) && sanitize_text_field( wp_unslash( $_POST['light'] ) ) === '1' );
        $s = LumiCode_Settings::get();
        $s['light_mode'] = $light;
        update_option( 'lumicode_settings', $s );
        wp_send_json_success( [ 'light_mode' => $light ] );
    }

    /* ── AJAX: flush asset cache via touch() ───────────────── */
    public static function ajax_flush_cache() {
        check_ajax_referer( 'lumicode_flush_cache', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( esc_html__( 'Unauthorized', 'lumicode-syntax-highlighter' ) );
        /*
         * touch() updates each file's mtime to "now".
         * Frontend uses filemtime() for the ?ver= query string,
         * so this busts browser and page-cache assets on next load.
         */
        $files = [
            LUMICODE_DIR . 'assets/css/lc-blocks.css',
            LUMICODE_DIR . 'assets/js/lc-render.js',
            LUMICODE_DIR . 'assets/css/admin-shared.css',
            LUMICODE_DIR . 'assets/css/admin-settings.css',
            LUMICODE_DIR . 'assets/js/admin-settings.js',
        ];
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        foreach ( $files as $f ) {
            if ( $wp_filesystem->exists( $f ) ) {
                $wp_filesystem->touch( $f );
            }
        }
        wp_send_json_success();
    }

    /* ── Menu ──────────────────────────────────────────────── */
    public static function register_menu() {
        add_menu_page(
            'LumiCode', 'LumiCode', 'manage_options',
            'lumicode', [ __CLASS__, 'render_settings' ],
            'dashicons-editor-code', 81
        );
        add_submenu_page( 'lumicode', esc_html__( 'Settings', 'lumicode-syntax-highlighter' ),     esc_html__( 'Settings', 'lumicode-syntax-highlighter' ),     'manage_options', 'lumicode',         [ __CLASS__, 'render_settings' ] );
        add_submenu_page( 'lumicode', esc_html__( 'Code Scanner', 'lumicode-syntax-highlighter' ), esc_html__( 'Code Scanner', 'lumicode-syntax-highlighter' ), 'edit_posts',     'lumicode-scanner', [ __CLASS__, 'render_scanner' ] );
    }

    /* ── Enqueue ───────────────────────────────────────────── */
    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'lumicode' ) === false ) return;

        $theme      = self::safe_theme();
        $is_scanner = ( strpos( $hook, 'lumicode-scanner' ) !== false );

        // Local assets — compliant with WP.org
        wp_enqueue_style( 'phosphor-icons',
            LUMICODE_URL . 'assets/vendor/css/phosphor/style.css',
            [], LUMICODE_VERSION );
        wp_enqueue_style( 'lumicode-hljs-theme-admin',
            LumiCode_Settings::theme_url( $theme ),
            [], LUMICODE_VERSION );
        wp_enqueue_script( 'lumicode-hljs-admin',
            LUMICODE_URL . 'assets/vendor/js/highlight.min.js',
            [], LUMICODE_VERSION, false );
        wp_enqueue_script( 'lumicode-admin-topbar',
            LUMICODE_URL . 'assets/js/admin-topbar.js',
            [], LUMICODE_VERSION, true );
        $s = LumiCode_Settings::get();
        $is_light = ! empty( $s['light_mode'] ) || in_array( $s['theme'] ?? '', LumiCode_Settings::light_themes(), true );
        wp_localize_script( 'lumicode-admin-topbar', 'LumiCodeTopbar', [
            'isLight' => $is_light,
        ] );

        /*
         * Admin CSS/JS assets.
         * Files use mtime-based versions for reliable cache busting.
         * Assets are enqueued as files so WordPress can manage versions.
         * Stylesheets and scripts are loaded through wp_enqueue_style/script.
         */
        wp_enqueue_style(
            'lumicode-admin-shared',
            LUMICODE_URL . 'assets/css/admin-shared.css',
            [ 'phosphor-icons', 'lumicode-hljs-theme-admin' ],
            self::asset_version( 'assets/css/admin-shared.css' )
        );

        $page_style = $is_scanner ? 'admin-scanner.css' : 'admin-settings.css';
        wp_enqueue_style(
            'lumicode-admin-page',
            LUMICODE_URL . 'assets/css/' . $page_style,
            [ 'lumicode-admin-shared' ],
            self::asset_version( 'assets/css/' . $page_style )
        );

        $page_script = $is_scanner ? 'admin-scanner.js' : 'admin-settings.js';
        wp_enqueue_script(
            'lumicode-admin-page',
            LUMICODE_URL . 'assets/js/' . $page_script,
            [ 'jquery', 'lumicode-hljs-admin' ],
            self::asset_version( 'assets/js/' . $page_script ),
            true
        );

        $s = LumiCode_Settings::get();
        if ( $is_scanner ) {
            wp_localize_script( 'lumicode-admin-page', 'LumiCodeAdmin',
                [
                    'ajax_url'       => admin_url( 'admin-ajax.php' ),
                    'nonce'          => wp_create_nonce( 'lumicode_scanner' ),
                    'lightModeNonce' => wp_create_nonce( 'lumicode_light_mode' ),
                    'isLightMode'    => ! empty( $s['light_mode'] ),
                    'i18n'           => [
                        'scanning'              => esc_html__( 'Scanning your site…', 'lumicode-syntax-highlighter' ),
                        'noResults'             => esc_html__( 'No unformatted code blocks found.', 'lumicode-syntax-highlighter' ),
                        'error'                 => esc_html__( 'Request failed. Please try again.', 'lumicode-syntax-highlighter' ),
                        'confirmApplyAll'       => esc_html__( 'Apply LumiCode formatting to all pending blocks? Reversible via post revisions.', 'lumicode-syntax-highlighter' ),
                        'confirmClearDismissed' => esc_html__( 'Show all previously dismissed blocks again?', 'lumicode-syntax-highlighter' ),
                        'appliedSuccess'        => esc_html__( 'Applied successfully', 'lumicode-syntax-highlighter' ),
                        'applying'              => esc_html__( 'Applying…', 'lumicode-syntax-highlighter' ),
                        'dismissed'             => esc_html__( 'Dismissed', 'lumicode-syntax-highlighter' ),
                        'pendingCount'          => esc_html__( 'pending', 'lumicode-syntax-highlighter' ),
                        'appliedCount'          => esc_html__( 'applied', 'lumicode-syntax-highlighter' ),
                        'dismissedCount'        => esc_html__( 'dismissed', 'lumicode-syntax-highlighter' ),
                    ],
                ]
            );
        } else {
            wp_localize_script( 'lumicode-admin-page', 'LumiCodeAdmin',
                [
                    'theme'          => $theme,
                    'localBase'      => LUMICODE_URL . 'assets/vendor/css/themes/',
                    'lightThemes'    => LumiCode_Settings::light_themes(),
                    'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                    'flushNonce'     => wp_create_nonce( 'lumicode_flush_cache' ),
                    'lightModeNonce' => wp_create_nonce( 'lumicode_light_mode' ),
                    'isLightMode'    => ! empty( $s['light_mode'] ),
                    'i18n'           => [
                        'saving'             => esc_html__( 'Saving…', 'lumicode-syntax-highlighter' ),
                        'saved'              => esc_html__( 'Saved!', 'lumicode-syntax-highlighter' ),
                        'error'              => esc_html__( 'Failed to save', 'lumicode-syntax-highlighter' ),
                        'clearingCache'      => esc_html__( 'Clearing cache…', 'lumicode-syntax-highlighter' ),
                        'cacheCleared'       => esc_html__( 'Cache cleared! Reload your frontend.', 'lumicode-syntax-highlighter' ),
                        'flushFailedPerms'   => esc_html__( 'Cache flush failed. Check server permissions on the assets folder.', 'lumicode-syntax-highlighter' ),
                        'flushFailedNetwork' => esc_html__( 'Cache flush failed. Could not reach WordPress AJAX.', 'lumicode-syntax-highlighter' ),
                        'savingSettings'     => esc_html__( 'Saving settings…', 'lumicode-syntax-highlighter' ),
                    ],
                ]
            );
        }
    }

    private static function asset_version( $relative ) {
        $path = LUMICODE_DIR . $relative;
        return file_exists( $path ) ? filemtime( $path ) : LUMICODE_VERSION;
    }

    private static function safe_theme() {
        $theme   = sanitize_text_field( LumiCode_Settings::get( 'theme' ) ?: 'atom-one-dark' );
        $allowed = array_keys( LumiCode_Settings::available_themes() );
        return in_array( $theme, $allowed, true ) ? $theme : 'atom-one-dark';
    }

    /* ══════════════════════════════════════════════════════════
       SETTINGS PAGE
    ══════════════════════════════════════════════════════════ */
    public static function render_settings() {
        $s      = LumiCode_Settings::get();
        $themes = LumiCode_Settings::available_themes();
        ?>
        <div class="wrap lc-outer-wrap">
        <?php settings_errors( 'lumicode_settings' ); ?>
        <div id="lc-wrap">

            <?php self::topbar( 'settings' ); ?>

            <div class="lc-body">
                <form method="post" action="options.php" id="lc-settings-form">
                    <?php settings_fields( 'lumicode_settings_group' ); ?>

                    <div class="lc-settings-grid">

                        <!-- LEFT COLUMN -->
                        <div class="lc-col">

                            <div class="lc-card">
                                <div class="lc-card-hd"><i class="ph ph-palette"></i> <?php esc_html_e( 'Appearance', 'lumicode-syntax-highlighter' ); ?></div>

                                <div class="lc-field">
                                    <label class="lc-lbl" for="lc-theme"><?php esc_html_e( 'Color Theme', 'lumicode-syntax-highlighter' ); ?></label>
                                    <select name="lumicode_settings[theme]" id="lc-theme">
                                        <?php foreach ( $themes as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected($s['theme'],$val); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="lc-hint"><?php esc_html_e( 'Preview updates live below.', 'lumicode-syntax-highlighter' ); ?></p>
                                </div>

                                <div class="lc-field">
                                    <label class="lc-lbl" for="lc-fontsize"><?php esc_html_e( 'Font Size', 'lumicode-syntax-highlighter' ); ?></label>
                                    <div class="lc-inline-row">
                                        <input type="number" id="lc-fontsize"
                                            name="lumicode_settings[font_size]"
                                            value="<?php echo esc_attr($s['font_size']); ?>"
                                            min="10" max="24">
                                        <span class="lc-unit">px</span>
                                    </div>
                                </div>

                                <div class="lc-field">
                                    <label class="lc-lbl" for="lc-fontfam"><?php esc_html_e( 'Font Family', 'lumicode-syntax-highlighter' ); ?></label>
                                    <input type="text" id="lc-fontfam"
                                        name="lumicode_settings[font_family]"
                                        value="<?php echo esc_attr($s['font_family']); ?>">
                                    <p class="lc-hint"><?php
                                        echo wp_kses(
                                            sprintf(
                                                /* translators: %s: example font stack */
                                                esc_html__( 'Comma-separated, e.g. %s', 'lumicode-syntax-highlighter' ),
                                                '<code>JetBrains Mono, monospace</code>'
                                            ),
                                            [ 'code' => [] ]
                                        );
                                    ?></p>
                                </div>
                            </div>

                            <div class="lc-card">
                                <div class="lc-card-hd"><i class="ph ph-sliders"></i> <?php esc_html_e( 'Features', 'lumicode-syntax-highlighter' ); ?></div>
                                <?php
                                $features = [
                                    'copy_button'    => [ esc_html__( 'Copy Button', 'lumicode-syntax-highlighter' ),    esc_html__( 'One-click copy on every code block', 'lumicode-syntax-highlighter' ) ],
                                    'line_numbers'   => [ esc_html__( 'Line Numbers', 'lumicode-syntax-highlighter' ),   esc_html__( 'Numbered gutter column on the left', 'lumicode-syntax-highlighter' ) ],
                                    'language_badge' => [ esc_html__( 'Language Badge', 'lumicode-syntax-highlighter' ), esc_html__( 'Shows detected language in the header bar', 'lumicode-syntax-highlighter' ) ],
                                    'auto_detect'    => [ esc_html__( 'Auto-Enhance', 'lumicode-syntax-highlighter' ),   esc_html__( 'Wrap bare &lt;pre&gt; tags sitewide', 'lumicode-syntax-highlighter' ) ],
                                ];
                                foreach ( $features as $key => [ $name, $desc ] ) :
                                    $on = ! empty( $s[ $key ] );
                                ?>
                                <label class="<?php echo esc_attr( 'lc-toggle' . ( $on ? ' is-on' : '' ) ); ?>">
                                    <input type="checkbox"
                                        name="lumicode_settings[<?php echo esc_attr($key); ?>]"
                                        value="1"<?php checked($on); ?>>
                                    <span class="lc-toggle-info">
                                        <span class="lc-toggle-name"><?php echo esc_html($name); ?></span>
                                        <span class="lc-toggle-desc"><?php echo esc_html($desc); ?></span>
                                    </span>
                                    <span class="lc-switch"><span class="lc-thumb"></span></span>
                                </label>
                                <?php endforeach; ?>

                                <!-- Light Mode toggle — bidirectionally synced with topbar button -->
                                <?php $lm_on = ! empty( $s['light_mode'] ); ?>
                                <label class="<?php echo esc_attr( 'lc-toggle' . ( $lm_on ? ' is-on' : '' ) ); ?>" id="lc-lightmode-toggle-row">
                                    <input type="checkbox" id="lc-lightmode-cb"
                                        name="lumicode_settings[light_mode]"
                                        value="1"<?php checked($lm_on); ?>>
                                    <span class="lc-toggle-info">
                                        <span class="lc-toggle-name"><?php esc_html_e( 'Light Mode', 'lumicode-syntax-highlighter' ); ?></span>
                                        <span class="lc-toggle-desc"><?php esc_html_e( 'Switch admin &amp; frontend code boxes to light theme', 'lumicode-syntax-highlighter' ); ?></span>
                                    </span>
                                    <span class="lc-switch"><span class="lc-thumb"></span></span>
                                </label>

                                <!-- Auto-collapse threshold -->
                                <div class="lc-field" style="margin-top:10px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.05);">
                                    <label class="lc-lbl" for="lc-collapse-after">
                                        <?php esc_html_e( 'Auto-Collapse After', 'lumicode-syntax-highlighter' ); ?>
                                        <span style="font-weight:400;color:rgba(148,163,184,0.45);font-size:10px;text-transform:none;letter-spacing:0;margin-left:4px;">(<?php esc_html_e( 'lines', 'lumicode-syntax-highlighter' ); ?>)</span>
                                    </label>
                                    <div class="lc-inline-row">
                                        <input type="number" id="lc-collapse-after"
                                            name="lumicode_settings[collapse_after]"
                                            value="<?php echo esc_attr($s['collapse_after']); ?>"
                                            min="0" max="500" style="width:80px;">
                                        <span class="lc-unit"><?php esc_html_e( 'lines', 'lumicode-syntax-highlighter' ); ?></span>
                                    </div>
                                    <p class="lc-hint"><?php
                                        echo wp_kses(
                                            sprintf(
                                                /* translators: %s: bold '0' */
                                                esc_html__( 'Code blocks longer than this auto-collapse. Set %s to disable.', 'lumicode-syntax-highlighter' ),
                                                '<strong>0</strong>'
                                            ),
                                            [ 'strong' => [] ]
                                        );
                                    ?></p>
                                </div>
                            </div>

                        </div><!-- /col left -->

                        <!-- RIGHT COLUMN -->
                        <div class="lc-col">
                            <div class="lc-card">
                                <div class="lc-card-hd"><i class="ph ph-eye"></i> Live Preview</div>
                                <div class="lc-pw" id="lc-preview-box">
                                    <div class="lc-pw-titlebar">
                                        <div class="lc-pw-dots" aria-hidden="true">
                                            <span class="lc-pw-dot lc-pw-dot--red"></span>
                                            <span class="lc-pw-dot lc-pw-dot--yellow"></span>
                                            <span class="lc-pw-dot lc-pw-dot--green"></span>
                                        </div>
                                        <div class="lc-pw-filename">
                                            <span class="lc-pw-dot-indicator"></span>
                                            fetchUser.ts
                                        </div>
                                        <button class="lc-pw-copybtn" type="button" tabindex="-1"><?php esc_html_e( 'Copy', 'lumicode-syntax-highlighter' ); ?></button>
                                    </div>
                                    <div class="lc-pw-code" id="lc-preview-code">
<pre><code class="language-typescript">async function fetchUser(
  userId: string,
  retries: number = 3
): Promise&lt;User&gt; {
  for (let i = 0; i &lt; retries; i++) {
    const res = await fetch(`/api/${userId}`);
    if (res.ok) return res.json();
  }
  throw new Error('Failed');
}</code></pre>
                                    </div>
                                    <div class="lc-pw-statusbar">
                                        <div class="lc-pw-status-left">
                                            <span><?php esc_html_e( 'Ln 1, Col 1', 'lumicode-syntax-highlighter' ); ?></span>
                                            <span><?php esc_html_e( 'UTF-8', 'lumicode-syntax-highlighter' ); ?></span>
                                            <span><?php esc_html_e( 'LF', 'lumicode-syntax-highlighter' ); ?></span>
                                        </div>
                                        <div class="lc-pw-status-right" id="lc-preview-lang"><?php esc_html_e( 'TypeScript', 'lumicode-syntax-highlighter' ); ?></div>
                                    </div>
                                </div>
                                <p class="lc-hint" style="text-align:center;margin-top:10px;"><?php esc_html_e( 'Theme updates live. Saves sitewide on submit.', 'lumicode-syntax-highlighter' ); ?></p>
                            </div>

                            <div class="lc-card">
                                <div class="lc-card-hd"><i class="ph ph-book-open"></i> <?php esc_html_e( 'Shortcode Reference', 'lumicode-syntax-highlighter' ); ?></div>
                                <pre class="lc-ref-pre">[lumicode lang="php" title="config.php"
         highlight="2,4-6" collapse="false"]
&lt;?php echo "Hello World"; ?&gt;
[/lumicode]</pre>
                                <table class="lc-ref-table">
                                    <tr><td>lang</td><td><?php esc_html_e( 'php, javascript, css, html… (optional, auto-detected)', 'lumicode-syntax-highlighter' ); ?></td></tr>
                                    <tr><td>title</td><td><?php esc_html_e( 'Filename shown in the header bar', 'lumicode-syntax-highlighter' ); ?></td></tr>
                                    <tr><td>highlight</td><td><?php
                                        echo wp_kses(
                                            sprintf(
                                                /* translators: %s: example line range */
                                                esc_html__( 'Lines to accent, e.g. %s', 'lumicode-syntax-highlighter' ),
                                                '<code>3,5-7</code>'
                                            ),
                                            [ 'code' => [] ]
                                        );
                                    ?></td></tr>
                                    <tr><td>collapse</td><td><?php
                                        echo wp_kses(
                                            sprintf(
                                                /* translators: %s: bold 'true' */
                                                esc_html__( '%s makes the block collapsible', 'lumicode-syntax-highlighter' ),
                                                '<code>true</code>'
                                            ),
                                            [ 'code' => [] ]
                                        );
                                    ?></td></tr>
                                </table>
                            </div>
                        </div><!-- /col right -->

                    </div><!-- /grid -->

                    <div class="lc-save-row">
                        <button type="submit" class="lc-btn lc-btn-primary">
                            <i class="ph ph-floppy-disk"></i> <?php esc_html_e( 'Save Settings', 'lumicode-syntax-highlighter' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=lumicode-scanner') ); ?>" class="lc-btn lc-btn-ghost">
                            <i class="ph ph-scan"></i> <?php esc_html_e( 'Open Scanner', 'lumicode-syntax-highlighter' ); ?>
                        </a>
                        <button type="button" id="lc-flush-cache" class="lc-btn lc-btn-ghost" title="<?php esc_attr_e( 'Forces browsers to reload the latest CSS and JS files.', 'lumicode-syntax-highlighter' ); ?>">
                            <i class="ph ph-arrow-counter-clockwise"></i> <?php esc_html_e( 'Flush Asset Cache', 'lumicode-syntax-highlighter' ); ?>
                        </button>
                        <span id="lc-saved-msg" class="lc-saved-msg"><?php esc_html_e( 'Settings saved!', 'lumicode-syntax-highlighter' ); ?></span>
                    </div>

                </form>
            </div>
        </div>
        </div>

        <!-- Frontend light mode modal — shown when topbar toggle changes light mode -->
        <div id="lc-frontend-modal" style="position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s ease;">
            <div style="background:#1c1e28;border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:32px 36px;max-width:420px;width:90%;box-shadow:0 32px 80px rgba(0,0,0,0.55);text-align:center;">
                <div id="lc-fmodal-icon" style="font-size:32px;margin-bottom:14px;"></div>
                <div style="font-size:15px;font-weight:700;color:#e2e8f0;margin-bottom:8px;" id="lc-fmodal-title"></div>
                <div style="font-size:13px;color:rgba(148,163,184,0.6);line-height:1.6;margin-bottom:24px;" id="lc-fmodal-body"></div>
                <div style="display:flex;gap:10px;justify-content:center;">
                    <button id="lc-fmodal-yes" class="lc-btn lc-btn-primary lc-btn-sm"><?php esc_html_e( 'Yes, apply to frontend', 'lumicode-syntax-highlighter' ); ?></button>
                    <button id="lc-fmodal-no"  class="lc-btn lc-btn-ghost lc-btn-sm"><?php esc_html_e( 'Admin only', 'lumicode-syntax-highlighter' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /* ══════════════════════════════════════════════════════════
       SCANNER PAGE
    ══════════════════════════════════════════════════════════ */
    public static function render_scanner() {
        $dismissed_count = count( LumiCode_Scanner::get_dismissed() );
        ?>
        <div class="wrap lc-outer-wrap">
        <div id="lc-wrap">

            <?php self::topbar( 'scanner' ); ?>

            <div class="lc-body">

                <div class="lc-info-row">
                    <div class="lc-info-card">
                        <span class="lc-info-icon lc-info-icon--purple"><i class="ph ph-shield-check"></i></span>
                        <div><strong><?php esc_html_e( 'Safe by design', 'lumicode-syntax-highlighter' ); ?></strong><span><?php esc_html_e( 'Nothing changes without your explicit approval per block.', 'lumicode-syntax-highlighter' ); ?></span></div>
                    </div>
                    <div class="lc-info-card">
                        <span class="lc-info-icon lc-info-icon--blue"><i class="ph ph-arrow-counter-clockwise"></i></span>
                        <div><strong><?php esc_html_e( 'Fully reversible', 'lumicode-syntax-highlighter' ); ?></strong><span><?php esc_html_e( 'All changes go through WordPress post revisions.', 'lumicode-syntax-highlighter' ); ?></span></div>
                    </div>
                    <div class="lc-info-card">
                        <span class="lc-info-icon lc-info-icon--green"><i class="ph ph-crosshair"></i></span>
                        <div><strong><?php esc_html_e( 'Per-block precision', 'lumicode-syntax-highlighter' ); ?></strong><span><?php esc_html_e( 'Each block has a fingerprint. One approval = one block.', 'lumicode-syntax-highlighter' ); ?></span></div>
                    </div>
                </div>

                <div class="lc-toolbar">
                    <button id="lc-run-scan" class="lc-btn lc-btn-primary">
                        <i class="ph ph-magnifying-glass"></i> <?php esc_html_e( 'Scan Posts &amp; Pages', 'lumicode-syntax-highlighter' ); ?>
                    </button>
                    <?php if ( $dismissed_count > 0 ) : ?>
                    <button id="lc-clear-dismissed" class="lc-btn lc-btn-ghost">
                        <i class="ph ph-arrow-counter-clockwise"></i>
                        <?php
                            /* translators: %s: number of dismissed blocks */
                            echo esc_html( sprintf( esc_html__( 'Show %s Dismissed', 'lumicode-syntax-highlighter' ), $dismissed_count ) );
                        ?>
                    </button>
                    <?php endif; ?>
                    <span id="lc-scan-status" class="lc-scan-status"></span>
                </div>

                <div id="lc-summary-bar" style="display:none;">
                    <div class="lc-summary-bar">
                        <div class="lc-chips">
                            <span id="lc-chip-pending"   class="lc-chip lc-chip-pending"><?php esc_html_e( '0 pending', 'lumicode-syntax-highlighter' ); ?></span>
                            <span id="lc-chip-applied"   class="lc-chip lc-chip-applied"><?php esc_html_e( '0 applied', 'lumicode-syntax-highlighter' ); ?></span>
                            <span id="lc-chip-dismissed" class="lc-chip lc-chip-dismissed"><?php esc_html_e( '0 dismissed', 'lumicode-syntax-highlighter' ); ?></span>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button id="lc-accept-all"  class="lc-btn lc-btn-primary lc-btn-sm"><i class="ph ph-check"></i> <?php esc_html_e( 'Accept All', 'lumicode-syntax-highlighter' ); ?></button>
                            <button id="lc-dismiss-all" class="lc-btn lc-btn-ghost lc-btn-sm"><i class="ph ph-x"></i> <?php esc_html_e( 'Dismiss All', 'lumicode-syntax-highlighter' ); ?></button>
                        </div>
                    </div>
                </div>

                <div id="lc-results-placeholder" class="lc-results-placeholder">
                    <div class="lc-placeholder-row-ghost">
                        <div class="lc-placeholder-row-ghost-hd">
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-title"></div>
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-actions"></div>
                        </div>
                        <div class="lc-placeholder-row-ghost-body">
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-line"></div>
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-line" style="width:72%"></div>
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-line" style="width:55%"></div>
                        </div>
                    </div>
                    <div class="lc-placeholder-row-ghost" style="opacity:0.55;">
                        <div class="lc-placeholder-row-ghost-hd">
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-title"></div>
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-actions"></div>
                        </div>
                        <div class="lc-placeholder-row-ghost-body">
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-line"></div>
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-line" style="width:80%"></div>
                        </div>
                    </div>
                    <div class="lc-placeholder-row-ghost" style="opacity:0.25;">
                        <div class="lc-placeholder-row-ghost-hd">
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-title"></div>
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-actions"></div>
                        </div>
                        <div class="lc-placeholder-row-ghost-body">
                            <div class="lc-placeholder-ghost-bar lc-placeholder-ghost-line" style="width:60%"></div>
                        </div>
                    </div>
                    <div class="lc-placeholder-overlay">
                        <div class="lc-placeholder-overlay-icon"><i class="ph ph-magnifying-glass"></i></div>
                        <p class="lc-placeholder-overlay-title"><?php esc_html_e( 'Ready to scan', 'lumicode-syntax-highlighter' ); ?></p>
                        <p class="lc-placeholder-overlay-sub"><?php
                            echo wp_kses(
                                sprintf(
                                    /* translators: %s: scanning button label */
                                    esc_html__( 'Click %s above to find code blocks that aren\'t yet using LumiCode formatting.', 'lumicode-syntax-highlighter' ),
                                    '<strong>' . esc_html__( 'Scan Posts &amp; Pages', 'lumicode-syntax-highlighter' ) . '</strong>'
                                ),
                                [ 'strong' => [] ]
                            );
                        ?></p>
                    </div>
                </div>

                <div id="lc-results"></div>

            </div>
        </div>
        </div>

        <div id="lc-scan-modal">
            <div class="lc-scan-modal-box">
                <div class="lc-scan-spinner"></div>
                <div class="lc-scan-modal-title"><?php esc_html_e( 'Scanning your site…', 'lumicode-syntax-highlighter' ); ?></div>
                <div class="lc-scan-modal-sub"><?php esc_html_e( 'Checking published posts and pages for bare &lt;pre&gt; blocks.', 'lumicode-syntax-highlighter' ); ?></div>
            </div>
        </div>

        <template id="lc-row-tpl">
            <div class="lc-row" data-fp="__FP__" data-pid="__PID__">
                <div class="lc-row-hd">
                    <div class="lc-row-hd-left">
                        <a class="lc-row-title" href="__EDIT__" target="_blank" rel="noopener noreferrer">__TITLE__</a>
                        <a class="lc-row-view"  href="__URL__"  target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'lumicode-syntax-highlighter' ); ?></a>
                    </div>
                    <div class="lc-row-actions">
                        <span class="lc-lang-label"><?php esc_html_e( 'Lang:', 'lumicode-syntax-highlighter' ); ?></span>
                        <select class="lc-lang-sel">
                            <option value=""><?php esc_html_e( 'Auto-detect', 'lumicode-syntax-highlighter' ); ?></option>
                            <option value="php">PHP</option>
                            <option value="javascript">JavaScript</option>
                            <option value="typescript">TypeScript</option>
                            <option value="python">Python</option>
                            <option value="html">HTML</option>
                            <option value="css">CSS</option>
                            <option value="bash">Bash / Shell</option>
                            <option value="sql">SQL</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                            <option value="java">Java</option>
                            <option value="go">Go</option>
                            <option value="rust">Rust</option>
                            <option value="yaml">YAML</option>
                        </select>
                        <button class="lc-apply-btn"><?php esc_html_e( 'Apply', 'lumicode-syntax-highlighter' ); ?></button>
                        <button class="lc-dismiss-btn" title="<?php esc_attr_e( 'Dismiss', 'lumicode-syntax-highlighter' ); ?>">&#x2715;</button>
                    </div>
                </div>
                <div class="lc-row-snippet">
                    <div class="lc-snippet-label"><?php esc_html_e( 'Code Preview', 'lumicode-syntax-highlighter' ); ?></div>
                    <pre class="lc-snippet-pre">__CODE__</pre>
                </div>
                <div class="lc-row-status"></div>
            </div>
        </template>
        <?php
    }

    /* ── Shared topbar ─────────────────────────────────────── */
    private static function topbar( $active ) {
        $url_s    = admin_url( 'admin.php?page=lumicode' );
        $url_c    = admin_url( 'admin.php?page=lumicode-scanner' );
        $s        = LumiCode_Settings::get();
        $is_light = ! empty( $s['light_mode'] ) || in_array( $s['theme'] ?? '', LumiCode_Settings::light_themes(), true );
        ?>
        <div class="lc-topbar">
            <a href="<?php echo esc_url($url_s); ?>" class="lc-logo">
                <span class="lc-logo-icon"><i class="ph ph-lightning"></i></span>
                LumiCode
            </a>
            <nav class="lc-topbar-nav">
                <a href="<?php echo esc_url($url_s); ?>"
                   class="<?php echo esc_attr( 'lc-nav' . ( $active === 'settings' ? ' is-active' : '' ) ); ?>">
                    <i class="ph ph-gear"></i> <?php esc_html_e( 'Settings', 'lumicode-syntax-highlighter' ); ?>
                </a>
                <a href="<?php echo esc_url($url_c); ?>"
                   class="<?php echo esc_attr( 'lc-nav' . ( $active === 'scanner' ? ' is-active' : '' ) ); ?>">
                    <i class="ph ph-scan"></i> <?php esc_html_e( 'Code Scanner', 'lumicode-syntax-highlighter' ); ?>
                </a>
            </nav>
            <div class="lc-topbar-meta">
                <button id="lc-mode-toggle" class="lc-btn lc-btn-ghost lc-btn-sm" type="button" title="Toggle dark / light mode">
                    <i class="<?php echo esc_attr( 'ph ' . ( $is_light ? 'ph-sun' : 'ph-moon' ) ); ?>" id="lc-mode-icon"></i>
                    <span id="lc-mode-label"><?php echo esc_html( $is_light ? 'Dark' : 'Light' ); ?></span>
                </button>
                <span class="lc-version-badge">v<?php echo esc_html(LUMICODE_VERSION); ?></span>
                <a href="https://cr8vstacks.com" target="_blank" rel="noopener noreferrer" class="lc-ext-link"><?php esc_html_e( 'Cr8v Stacks', 'lumicode-syntax-highlighter' ); ?> ↗</a>
            </div>
        </div>
        <?php
    }
}
