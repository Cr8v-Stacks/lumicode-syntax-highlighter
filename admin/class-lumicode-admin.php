<?php
/**
 * LumiCode Admin — v1.5.2
 * Icons: Phosphor Icons web font via CDN (unpkg.com/@phosphor-icons/web)
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
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Unauthorized', 'lumicode' ) );
        $light = ( isset( $_POST['light'] ) && $_POST['light'] === '1' );
        $s = LumiCode_Settings::get();
        $s['light_mode'] = $light;
        update_option( 'lumicode_settings', $s );
        wp_send_json_success( [ 'light_mode' => $light ] );
    }

    /* ── AJAX: flush asset cache via touch() ───────────────── */
    public static function ajax_flush_cache() {
        check_ajax_referer( 'lumicode_flush_cache', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Unauthorized', 'lumicode' ) );
        /*
         * touch() updates each file's mtime to "now".
         * Frontend uses filemtime() for the ?ver= query string,
         * so this busts browser, CDN, and Cloudflare caches on next load.
         */
        $files = [
            LUMICODE_DIR . 'assets/css/lc-blocks.css',
            LUMICODE_DIR . 'assets/js/lc-render.js',
            LUMICODE_DIR . 'assets/css/admin-shared.css',
            LUMICODE_DIR . 'assets/css/admin-settings.css',
            LUMICODE_DIR . 'assets/js/admin-settings.js',
        ];
        foreach ( $files as $f ) {
            if ( file_exists( $f ) ) @touch( $f );
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
        add_submenu_page( 'lumicode', __( 'Settings', 'lumicode' ),     __( 'Settings', 'lumicode' ),     'manage_options', 'lumicode',         [ __CLASS__, 'render_settings' ] );
        add_submenu_page( 'lumicode', __( 'Code Scanner', 'lumicode' ), __( 'Code Scanner', 'lumicode' ), 'edit_posts',     'lumicode-scanner', [ __CLASS__, 'render_scanner' ] );
    }

    /* ── Enqueue ───────────────────────────────────────────── */
    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'lumicode' ) === false ) return;

        $theme      = self::safe_theme();
        $is_scanner = ( strpos( $hook, 'lumicode-scanner' ) !== false );

        // Local assets — compliant with WP.org
        wp_enqueue_style( 'phosphor-icons',
            LUMICODE_URL . 'assets/vendor/css/phosphor/style.css',
            [], null );
        wp_enqueue_style( 'lumicode-hljs-theme-admin',
            LumiCode_Settings::theme_url( $theme ),
            [], null );
        wp_enqueue_script( 'lumicode-hljs-admin',
            LUMICODE_URL . 'assets/vendor/js/highlight.min.js',
            [], null, false );

        /*
         * INLINE CSS/JS — CSS and JS content embedded directly in the HTML page.
         * This is the most cache-proof approach possible: no external file request,
         * so no browser/CDN/proxy can cache a stale version.
         * We read the raw file content and inject it via wp_add_inline_style/script.
         */
        wp_register_style( 'lumicode-admin-inline', false,
            [ 'phosphor-icons', 'lumicode-hljs-theme-admin' ] );
        wp_enqueue_style( 'lumicode-admin-inline' );
        wp_add_inline_style( 'lumicode-admin-inline',
            self::read_asset( 'assets/css/admin-shared.css' ) .
            ( $is_scanner
                ? self::read_asset( 'assets/css/admin-scanner.css' )
                : self::read_asset( 'assets/css/admin-settings.css' )
            )
        );

        wp_register_script( 'lumicode-admin-inline-js', false,
            [ 'jquery', 'lumicode-hljs-admin' ], false, true );
        wp_enqueue_script( 'lumicode-admin-inline-js' );
        wp_add_inline_script( 'lumicode-admin-inline-js',
            $is_scanner
                ? self::read_asset( 'assets/js/admin-scanner.js' )
                : self::read_asset( 'assets/js/admin-settings.js' )
        );

        $s = LumiCode_Settings::get();
        if ( $is_scanner ) {
            wp_add_inline_script( 'lumicode-admin-inline-js',
                'window.LumiCodeAdmin = ' . json_encode( [
                    'ajax_url'       => admin_url( 'admin-ajax.php' ),
                    'nonce'          => wp_create_nonce( 'lumicode_scanner' ),
                    'lightModeNonce' => wp_create_nonce( 'lumicode_light_mode' ),
                    'isLightMode'    => ! empty( $s['light_mode'] ),
                    'i18n'           => [
                        'scanning'              => __( 'Scanning your site…', 'lumicode' ),
                        'noResults'             => __( 'No unformatted code blocks found.', 'lumicode' ),
                        'error'                 => __( 'Request failed. Please try again.', 'lumicode' ),
                        'confirmApplyAll'       => __( 'Apply LumiCode formatting to all pending blocks? Reversible via post revisions.', 'lumicode' ),
                        'confirmClearDismissed' => __( 'Show all previously dismissed blocks again?', 'lumicode' ),
                        'appliedSuccess'        => __( 'Applied successfully', 'lumicode' ),
                        'applying'              => __( 'Applying…', 'lumicode' ),
                        'dismissed'             => __( 'Dismissed', 'lumicode' ),
                        'pendingCount'          => __( 'pending', 'lumicode' ),
                        'appliedCount'          => __( 'applied', 'lumicode' ),
                        'dismissedCount'        => __( 'dismissed', 'lumicode' ),
                    ],
                ] ) . ';',
                'before'
            );
        } else {
            wp_add_inline_script( 'lumicode-admin-inline-js',
                'window.LumiCodeAdmin = ' . json_encode( [
                    'theme'          => $theme,
                    'localBase'      => LUMICODE_URL . 'assets/vendor/css/themes/',
                    'lightThemes'    => LumiCode_Settings::light_themes(),
                    'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                    'flushNonce'     => wp_create_nonce( 'lumicode_flush_cache' ),
                    'lightModeNonce' => wp_create_nonce( 'lumicode_light_mode' ),
                    'isLightMode'    => ! empty( $s['light_mode'] ),
                    'i18n'           => [
                        'saving'             => __( 'Saving…', 'lumicode' ),
                        'saved'              => __( 'Saved!', 'lumicode' ),
                        'error'              => __( 'Failed to save', 'lumicode' ),
                        'clearingCache'      => __( 'Clearing cache…', 'lumicode' ),
                        'cacheCleared'       => __( 'Cache cleared! Reload your frontend.', 'lumicode' ),
                        'flushFailedPerms'   => __( 'Cache flush failed. Check server permissions on the assets folder.', 'lumicode' ),
                        'flushFailedNetwork' => __( 'Cache flush failed. Could not reach WordPress AJAX.', 'lumicode' ),
                        'savingSettings'     => __( 'Saving settings…', 'lumicode' ),
                    ]
                ] ) . ';',
                'before'
            );
        }
    }

    private static function read_asset( $relative ) {
        $path = LUMICODE_DIR . $relative;
        return file_exists( $path ) ? file_get_contents( $path ) : '';
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
                                <div class="lc-card-hd"><i class="ph ph-palette"></i> <?php _e( 'Appearance', 'lumicode' ); ?></div>

                                <div class="lc-field">
                                    <label class="lc-lbl" for="lc-theme"><?php _e( 'Color Theme', 'lumicode' ); ?></label>
                                    <select name="lumicode_settings[theme]" id="lc-theme">
                                        <?php foreach ( $themes as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected($s['theme'],$val); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="lc-hint"><?php _e( 'Preview updates live below.', 'lumicode' ); ?></p>
                                </div>

                                <div class="lc-field">
                                    <label class="lc-lbl" for="lc-fontsize"><?php _e( 'Font Size', 'lumicode' ); ?></label>
                                    <div class="lc-inline-row">
                                        <input type="number" id="lc-fontsize"
                                            name="lumicode_settings[font_size]"
                                            value="<?php echo esc_attr($s['font_size']); ?>"
                                            min="10" max="24">
                                        <span class="lc-unit">px</span>
                                    </div>
                                </div>

                                <div class="lc-field">
                                    <label class="lc-lbl" for="lc-fontfam"><?php _e( 'Font Family', 'lumicode' ); ?></label>
                                    <input type="text" id="lc-fontfam"
                                        name="lumicode_settings[font_family]"
                                        value="<?php echo esc_attr($s['font_family']); ?>">
                                    <p class="lc-hint"><?php printf( __( 'Comma-separated, e.g. %s', 'lumicode' ), '<code>JetBrains Mono, monospace</code>' ); ?></p>
                                </div>
                            </div>

                            <div class="lc-card">
                                <div class="lc-card-hd"><i class="ph ph-sliders"></i> <?php _e( 'Features', 'lumicode' ); ?></div>
                                <?php
                                $features = [
                                    'copy_button'    => [ __( 'Copy Button', 'lumicode' ),    __( 'One-click copy on every code block', 'lumicode' ) ],
                                    'line_numbers'   => [ __( 'Line Numbers', 'lumicode' ),   __( 'Numbered gutter column on the left', 'lumicode' ) ],
                                    'language_badge' => [ __( 'Language Badge', 'lumicode' ), __( 'Shows detected language in the header bar', 'lumicode' ) ],
                                    'auto_detect'    => [ __( 'Auto-Enhance', 'lumicode' ),   __( 'Wrap bare &lt;pre&gt; tags sitewide', 'lumicode' ) ],
                                ];
                                foreach ( $features as $key => [ $name, $desc ] ) :
                                    $on = ! empty( $s[ $key ] );
                                ?>
                                <label class="lc-toggle<?php echo $on ? ' is-on' : ''; ?>">
                                    <input type="checkbox"
                                        name="lumicode_settings[<?php echo esc_attr($key); ?>]"
                                        value="1"<?php checked($on); ?>>
                                    <span class="lc-toggle-info">
                                        <span class="lc-toggle-name"><?php echo esc_html($name); ?></span>
                                        <span class="lc-toggle-desc"><?php echo $desc; ?></span>
                                    </span>
                                    <span class="lc-switch"><span class="lc-thumb"></span></span>
                                </label>
                                <?php endforeach; ?>

                                <!-- Light Mode toggle — bidirectionally synced with topbar button -->
                                <?php $lm_on = ! empty( $s['light_mode'] ); ?>
                                <label class="lc-toggle<?php echo $lm_on ? ' is-on' : ''; ?>" id="lc-lightmode-toggle-row">
                                    <input type="checkbox" id="lc-lightmode-cb"
                                        name="lumicode_settings[light_mode]"
                                        value="1"<?php checked($lm_on); ?>>
                                    <span class="lc-toggle-info">
                                        <span class="lc-toggle-name"><?php _e( 'Light Mode', 'lumicode' ); ?></span>
                                        <span class="lc-toggle-desc"><?php _e( 'Switch admin &amp; frontend code boxes to light theme', 'lumicode' ); ?></span>
                                    </span>
                                    <span class="lc-switch"><span class="lc-thumb"></span></span>
                                </label>

                                <!-- Auto-collapse threshold -->
                                <div class="lc-field" style="margin-top:10px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.05);">
                                    <label class="lc-lbl" for="lc-collapse-after">
                                        <?php _e( 'Auto-Collapse After', 'lumicode' ); ?>
                                        <span style="font-weight:400;color:rgba(148,163,184,0.45);font-size:10px;text-transform:none;letter-spacing:0;margin-left:4px;">(<?php _e( 'lines', 'lumicode' ); ?>)</span>
                                    </label>
                                    <div class="lc-inline-row">
                                        <input type="number" id="lc-collapse-after"
                                            name="lumicode_settings[collapse_after]"
                                            value="<?php echo esc_attr($s['collapse_after']); ?>"
                                            min="0" max="500" style="width:80px;">
                                        <span class="lc-unit"><?php _e( 'lines', 'lumicode' ); ?></span>
                                    </div>
                                    <p class="lc-hint"><?php printf( __( 'Code blocks longer than this auto-collapse. Set %s to disable.', 'lumicode' ), '<strong>0</strong>' ); ?></p>
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
                                        <button class="lc-pw-copybtn" type="button" tabindex="-1"><?php _e( 'Copy', 'lumicode' ); ?></button>
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
                                            <span><?php _e( 'Ln 1, Col 1', 'lumicode' ); ?></span>
                                            <span><?php _e( 'UTF-8', 'lumicode' ); ?></span>
                                            <span><?php _e( 'LF', 'lumicode' ); ?></span>
                                        </div>
                                        <div class="lc-pw-status-right" id="lc-preview-lang"><?php _e( 'TypeScript', 'lumicode' ); ?></div>
                                    </div>
                                </div>
                                <p class="lc-hint" style="text-align:center;margin-top:10px;"><?php _e( 'Theme updates live. Saves sitewide on submit.', 'lumicode' ); ?></p>
                            </div>

                            <div class="lc-card">
                                <div class="lc-card-hd"><i class="ph ph-book-open"></i> <?php _e( 'Shortcode Reference', 'lumicode' ); ?></div>
                                <pre class="lc-ref-pre">[lumicode lang="php" title="config.php"
         highlight="2,4-6" collapse="false"]
&lt;?php echo "Hello World"; ?&gt;
[/lumicode]</pre>
                                <table class="lc-ref-table">
                                    <tr><td>lang</td><td><?php _e( 'php, javascript, css, html… (optional, auto-detected)', 'lumicode' ); ?></td></tr>
                                    <tr><td>title</td><td><?php _e( 'Filename shown in the header bar', 'lumicode' ); ?></td></tr>
                                    <tr><td>highlight</td><td><?php printf( __( 'Lines to accent, e.g. %s', 'lumicode' ), '<code>3,5-7</code>' ); ?></td></tr>
                                    <tr><td>collapse</td><td><?php printf( __( '%s makes the block collapsible', 'lumicode' ), '<code>true</code>' ); ?></td></tr>
                                </table>
                            </div>
                        </div><!-- /col right -->

                    </div><!-- /grid -->

                    <div class="lc-save-row">
                        <button type="submit" class="lc-btn lc-btn-primary">
                            <i class="ph ph-floppy-disk"></i> <?php _e( 'Save Settings', 'lumicode' ); ?>
                        </button>
                        <a href="<?php echo esc_url( admin_url('admin.php?page=lumicode-scanner') ); ?>" class="lc-btn lc-btn-ghost">
                            <i class="ph ph-scan"></i> <?php _e( 'Open Scanner', 'lumicode' ); ?>
                        </a>
                        <button type="button" id="lc-flush-cache" class="lc-btn lc-btn-ghost" title="<?php esc_attr_e( 'Forces browsers to reload the latest CSS and JS files.', 'lumicode' ); ?>">
                            <i class="ph ph-arrow-counter-clockwise"></i> <?php _e( 'Flush Asset Cache', 'lumicode' ); ?>
                        </button>
                        <span id="lc-saved-msg" class="lc-saved-msg"><?php _e( 'Settings saved!', 'lumicode' ); ?></span>
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
                    <button id="lc-fmodal-yes" class="lc-btn lc-btn-primary lc-btn-sm"><?php _e( 'Yes, apply to frontend', 'lumicode' ); ?></button>
                    <button id="lc-fmodal-no"  class="lc-btn lc-btn-ghost lc-btn-sm"><?php _e( 'Admin only', 'lumicode' ); ?></button>
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
                        <div><strong><?php _e( 'Safe by design', 'lumicode' ); ?></strong><span><?php _e( 'Nothing changes without your explicit approval per block.', 'lumicode' ); ?></span></div>
                    </div>
                    <div class="lc-info-card">
                        <span class="lc-info-icon lc-info-icon--blue"><i class="ph ph-arrow-counter-clockwise"></i></span>
                        <div><strong><?php _e( 'Fully reversible', 'lumicode' ); ?></strong><span><?php _e( 'All changes go through WordPress post revisions.', 'lumicode' ); ?></span></div>
                    </div>
                    <div class="lc-info-card">
                        <span class="lc-info-icon lc-info-icon--green"><i class="ph ph-crosshair"></i></span>
                        <div><strong><?php _e( 'Per-block precision', 'lumicode' ); ?></strong><span><?php _e( 'Each block has a fingerprint. One approval = one block.', 'lumicode' ); ?></span></div>
                    </div>
                </div>

                <div class="lc-toolbar">
                    <button id="lc-run-scan" class="lc-btn lc-btn-primary">
                        <i class="ph ph-magnifying-glass"></i> <?php _e( 'Scan Posts &amp; Pages', 'lumicode' ); ?>
                    </button>
                    <?php if ( $dismissed_count > 0 ) : ?>
                    <button id="lc-clear-dismissed" class="lc-btn lc-btn-ghost">
                        <i class="ph ph-arrow-counter-clockwise"></i>
                        <?php printf( __( 'Show %s Dismissed', 'lumicode' ), esc_html($dismissed_count) ); ?>
                    </button>
                    <?php endif; ?>
                    <span id="lc-scan-status" class="lc-scan-status"></span>
                </div>

                <div id="lc-summary-bar" style="display:none;">
                    <div class="lc-summary-bar">
                        <div class="lc-chips">
                            <span id="lc-chip-pending"   class="lc-chip lc-chip-pending"><?php _e( '0 pending', 'lumicode' ); ?></span>
                            <span id="lc-chip-applied"   class="lc-chip lc-chip-applied"><?php _e( '0 applied', 'lumicode' ); ?></span>
                            <span id="lc-chip-dismissed" class="lc-chip lc-chip-dismissed"><?php _e( '0 dismissed', 'lumicode' ); ?></span>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <button id="lc-accept-all"  class="lc-btn lc-btn-primary lc-btn-sm"><i class="ph ph-check"></i> <?php _e( 'Accept All', 'lumicode' ); ?></button>
                            <button id="lc-dismiss-all" class="lc-btn lc-btn-ghost lc-btn-sm"><i class="ph ph-x"></i> <?php _e( 'Dismiss All', 'lumicode' ); ?></button>
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
                        <p class="lc-placeholder-overlay-title"><?php _e( 'Ready to scan', 'lumicode' ); ?></p>
                        <p class="lc-placeholder-overlay-sub"><?php printf( __( 'Click %s above to find code blocks that aren\'t yet using LumiCode formatting.', 'lumicode' ), '<strong>' . __( 'Scan Posts &amp; Pages', 'lumicode' ) . '</strong>' ); ?></p>
                    </div>
                </div>

                <div id="lc-results"></div>

            </div>
        </div>
        </div>

        <div id="lc-scan-modal">
            <div class="lc-scan-modal-box">
                <div class="lc-scan-spinner"></div>
                <div class="lc-scan-modal-title"><?php _e( 'Scanning your site…', 'lumicode' ); ?></div>
                <div class="lc-scan-modal-sub"><?php _e( 'Checking published posts and pages for bare &lt;pre&gt; blocks.', 'lumicode' ); ?></div>
            </div>
        </div>

        <template id="lc-row-tpl">
            <div class="lc-row" data-fp="__FP__" data-pid="__PID__">
                <div class="lc-row-hd">
                    <div class="lc-row-hd-left">
                        <a class="lc-row-title" href="__EDIT__" target="_blank">__TITLE__</a>
                        <a class="lc-row-view"  href="__URL__"  target="_blank"><?php _e( 'View', 'lumicode' ); ?></a>
                    </div>
                    <div class="lc-row-actions">
                        <span class="lc-lang-label"><?php _e( 'Lang:', 'lumicode' ); ?></span>
                        <select class="lc-lang-sel">
                            <option value=""><?php _e( 'Auto-detect', 'lumicode' ); ?></option>
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
                        <button class="lc-apply-btn"><?php _e( 'Apply', 'lumicode' ); ?></button>
                        <button class="lc-dismiss-btn" title="<?php esc_attr_e( 'Dismiss', 'lumicode' ); ?>">&#x2715;</button>
                    </div>
                </div>
                <div class="lc-row-snippet">
                    <div class="lc-snippet-label"><?php _e( 'Code Preview', 'lumicode' ); ?></div>
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
                   class="lc-nav<?php echo $active === 'settings' ? ' is-active' : ''; ?>">
                    <i class="ph ph-gear"></i> Settings
                </a>
                <a href="<?php echo esc_url($url_c); ?>"
                   class="lc-nav<?php echo $active === 'scanner' ? ' is-active' : ''; ?>">
                    <i class="ph ph-scan"></i> Code Scanner
                </a>
            </nav>
            <div class="lc-topbar-meta">
                <button id="lc-mode-toggle" class="lc-btn lc-btn-ghost lc-btn-sm" type="button" title="Toggle dark / light mode">
                    <i class="ph <?php echo $is_light ? 'ph-sun' : 'ph-moon'; ?>" id="lc-mode-icon"></i>
                    <span id="lc-mode-label"><?php echo $is_light ? 'Dark' : 'Light'; ?></span>
                </button>
                <span class="lc-version-badge">v<?php echo esc_html(LUMICODE_VERSION); ?></span>
                <a href="https://cr8vstacks.com" target="_blank" class="lc-ext-link"><?php _e( 'Cr8v Stacks', 'lumicode' ); ?> ↗</a>
            </div>
        </div>
        <script>
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
         *
         * NOTE: This inline script runs synchronously at parse time.
         *   LumiCodeAdmin is set by a deferred script, so we read it lazily inside handlers.
         */
        (function() {
            var wrap    = document.getElementById('lc-wrap');
            var btn     = document.getElementById('lc-mode-toggle');
            var icon    = document.getElementById('lc-mode-icon');
            var label   = document.getElementById('lc-mode-label');
            var isLight = <?php echo $is_light ? 'true' : 'false'; ?>;
            var modalPending = null; /* pending light value waiting for modal confirmation */

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
        })();
        </script>
        <?php
    }
}
