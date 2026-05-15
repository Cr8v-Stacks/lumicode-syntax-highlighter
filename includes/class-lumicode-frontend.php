<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Frontend {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_filter( 'the_content',        [ __CLASS__, 'auto_enhance' ], 20 );
    }

    public static function enqueue() {
        $s        = LumiCode_Settings::get();
        $theme    = sanitize_text_field( $s['theme'] ?: 'atom-one-dark' );
        $allowed  = array_keys( LumiCode_Settings::available_themes() );
        if ( ! in_array( $theme, $allowed, true ) ) $theme = 'atom-one-dark';

        $is_light = ! empty( $s['light_mode'] ) || in_array( $theme, LumiCode_Settings::light_themes(), true );

        /*
         * PERFORMANCE:
         * - Our CSS/JS use filemtime() versioning for reliable cache busting.
         * - lc-blocks.css loads in <head> so code blocks paint styled on first frame.
         * - lc-render.js loads in footer (in_footer=true) with dependency on hljs.
         */
        $css_path = LUMICODE_DIR . 'assets/css/lc-blocks.css';
        $js_path  = LUMICODE_DIR . 'assets/js/lc-render.js';
        $css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : LUMICODE_VERSION;
        $js_ver   = file_exists( $js_path  ) ? filemtime( $js_path  ) : LUMICODE_VERSION;

        // Local assets: hljs theme CSS in <head>, hljs JS in footer
        wp_enqueue_style(  'lumicode-hljs-theme', LumiCode_Settings::theme_url( $theme ), [], LUMICODE_VERSION );
        
        $local_hljs_js = LUMICODE_URL . 'assets/vendor/js/highlight.min.js';
        wp_enqueue_script( 'lumicode-hljs', $local_hljs_js, [], LUMICODE_VERSION, true );

        // Our CSS in <head> (no in_footer arg = head) — blocks paint until ready, but tiny file
        wp_enqueue_style( 'lumicode-blocks',
            LUMICODE_URL . 'assets/css/lc-blocks.css',
            [ 'lumicode-hljs-theme' ], $css_ver );

        // Config object must arrive BEFORE lc-render.js runs
        wp_register_script( 'lumicode-main',
            LUMICODE_URL . 'assets/js/lc-render.js',
            [ 'lumicode-hljs' ], $js_ver, true ); // true = footer
        wp_enqueue_script( 'lumicode-main' );

        wp_add_inline_script( 'lumicode-main',
            'window.LumiCode = ' . wp_json_encode( [
                'theme'         => $theme,
                'isLight'       => $is_light,
                'lineNumbers'   => (bool) $s['line_numbers'],
                'copyButton'    => (bool) $s['copy_button'],
                'languageBadge' => (bool) $s['language_badge'],
                'autoDetect'    => (bool) $s['auto_detect'],
                'fontSize'      => (int) $s['font_size'],
                'fontFamily'    => $s['font_family'],
                'collapseAfter' => (int) $s['collapse_after'],
                'i18n'          => [
                    'copy'      => __( 'Copy', 'lumicode-syntax-highlighter' ),
                    'copied'    => __( 'Copied!', 'lumicode-syntax-highlighter' ),
                    'expand'    => __( 'Expand', 'lumicode-syntax-highlighter' ),
                    'collapse'  => __( 'Collapse', 'lumicode-syntax-highlighter' ),
                    'hidden'    => __( 'lines hidden', 'lumicode-syntax-highlighter' ),
                    'copyLabel' => __( 'Copy code', 'lumicode-syntax-highlighter' ),
                ],
            ] ) . ';',
            'before'
        );
    }

    public static function auto_enhance( $content ) {
        if ( ! LumiCode_Settings::get( 'auto_detect' ) ) return $content;
        $content = preg_replace_callback(
            '/<pre(?![^>]*class=["\'][^"\']*lumicode-pre)([^>]*)>([\s\S]*?)<\/pre>/i',
            function( $m ) {
                $attrs = $m[1]; $inner = $m[2]; $lang = '';
                if ( preg_match( '/class=["\']([^"\']*)["\']/', $attrs, $cm ) )
                    if ( preg_match( '/(?:language|lang)-(\w+)/', $cm[1], $lm ) ) $lang = $lm[1];
                if ( ! $lang && preg_match( '/<code[^>]+class=["\'][^"\']*(?:language|lang)-(\w+)["\']/', $inner, $lm ) )
                    $lang = $lm[1];
                $lang_attr = $lang ? ' data-lang="' . esc_attr( $lang ) . '"' : '';
                if ( preg_match( '/class=["\']([^"\']*)["\']/', $attrs ) )
                    $attrs = preg_replace( '/class=["\']([^"\']*)["\']/', 'class="$1 lumicode-pre"', $attrs );
                else $attrs .= ' class="lumicode-pre"';
                return '<pre' . $attrs . $lang_attr . '>' . $inner . '</pre>';
            }, $content );
        return $content;
    }
}
