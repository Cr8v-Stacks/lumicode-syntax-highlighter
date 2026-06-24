<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Settings {

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function get( $key = null ) {
        $defaults = [
            'theme'          => 'atom-one-dark',
            'light_mode'     => false,
            'line_numbers'   => true,
            'copy_button'    => true,
            'language_badge' => true,
            'font_size'      => '14',
            'font_family'    => 'JetBrains Mono, Fira Code, monospace',
            'auto_detect'    => true,
            'collapse_after' => 30,  // Auto-collapse blocks with more than N lines (0 = disabled)
            'max_width'      => '',
            'line_wrap'      => false,
        ];
        $settings = wp_parse_args( get_option( 'lumicode_settings', [] ), $defaults );
        if ( $key ) return $settings[ $key ] ?? null;
        return $settings;
    }

    public static function register_settings() {
        register_setting( 'lumicode_settings_group', 'lumicode_settings', [
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
        ] );
    }

    public static function sanitize( $input ) {
        $clean = [];
        $theme = sanitize_text_field( $input['theme'] ?? 'atom-one-dark' );
        $allowed = array_keys( self::available_themes() );
        $clean['theme']          = in_array( $theme, $allowed, true ) ? $theme : 'atom-one-dark';
        $clean['light_mode']     = ! empty( $input['light_mode'] );
        $clean['line_numbers']   = ! empty( $input['line_numbers'] );
        $clean['copy_button']    = ! empty( $input['copy_button'] );
        $clean['language_badge'] = ! empty( $input['language_badge'] );
        $clean['font_size']      = absint( $input['font_size'] ?? 14 );
        $clean['font_family']    = sanitize_text_field( $input['font_family'] ?? 'monospace' );
        $clean['auto_detect']    = ! empty( $input['auto_detect'] );
        $clean['collapse_after'] = absint( $input['collapse_after'] ?? 30 );
        $clean['max_width']      = sanitize_text_field( $input['max_width'] ?? '' );
        $clean['line_wrap']      = ! empty( $input['line_wrap'] );
        return $clean;
    }

    public static function available_themes() {
        return [
            'atom-one-dark'          => 'Atom One Dark',
            'atom-one-light'         => 'Atom One Light',
            'dracula'                => 'Dracula',
            'github'                 => 'GitHub Light',
            'github-dark'            => 'GitHub Dark',
            'monokai'                => 'Monokai',
            'monokai-sublime'        => 'Monokai Sublime',
            'night-owl'              => 'Night Owl',
            'nord'                   => 'Nord',
            'base16/solarized-dark'  => 'Solarized Dark',
            'base16/solarized-light' => 'Solarized Light',
            'vs2015'                 => 'VS 2015',
            'xcode'                  => 'Xcode',
        ];
    }

    public static function theme_url( $theme ) {
        $file = sanitize_text_field( $theme ) . '.min.css';
        $path = LUMICODE_DIR . 'assets/vendor/css/themes/' . $file;

        if ( ! file_exists( $path ) ) {
            // Fallback to locally-bundled default theme — no external requests.
            return LUMICODE_URL . 'assets/vendor/css/themes/atom-one-dark.min.css';
        }

        return LUMICODE_URL . 'assets/vendor/css/themes/' . $file;
    }

    public static function light_themes() {
        return [ 'atom-one-light', 'github', 'base16/solarized-light', 'xcode' ];
    }
}
