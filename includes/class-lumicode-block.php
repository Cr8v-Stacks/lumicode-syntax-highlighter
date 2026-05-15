<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Block {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_block' ] );
    }

    public static function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) return;

        register_block_type( 'lumicode/code-block', [
            'attributes'      => [
                'code'      => [ 'type' => 'string', 'default' => '' ],
                'language'  => [ 'type' => 'string', 'default' => '' ],
                'highlight' => [ 'type' => 'string', 'default' => '' ],
                'title'     => [ 'type' => 'string', 'default' => '' ],
                'collapse'  => [ 'type' => 'boolean', 'default' => false ],
            ],
            'render_callback' => [ __CLASS__, 'render_block' ],
            'editor_script'   => 'lumicode-block-editor',
        ] );

        wp_register_script(
            'lumicode-block-editor',
            LUMICODE_URL . 'assets/js/block-editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ],
            LUMICODE_VERSION,
            true
        );
        wp_localize_script( 'lumicode-block-editor', 'LumiCodeBlockI18n', [
            'title'       => __( 'LumiCode Block', 'lumicode-syntax-highlighter' ),
            'settings'    => __( 'LumiCode Settings', 'lumicode-syntax-highlighter' ),
            'language'    => __( 'Language', 'lumicode-syntax-highlighter' ),
            'titleLabel'  => __( 'Title / filename (optional)', 'lumicode-syntax-highlighter' ),
            'highlight'   => __( 'Highlight lines (e.g. 3,5-7)', 'lumicode-syntax-highlighter' ),
            'collapsible' => __( 'Collapsible', 'lumicode-syntax-highlighter' ),
            'placeholder' => __( 'Paste your code here...', 'lumicode-syntax-highlighter' ),
            'autoDetect'  => __( 'Auto-detect', 'lumicode-syntax-highlighter' ),
        ] );
    }

    public static function render_block( $attrs ) {
        $code      = $attrs['code']      ?? '';
        $lang      = $attrs['language']  ?? '';
        $highlight = $attrs['highlight'] ?? '';
        $title     = $attrs['title']     ?? '';
        $collapse  = ! empty( $attrs['collapse'] ) ? 'true' : 'false';

        $data_attrs  = '';
        if ( $lang )      $data_attrs .= ' data-lang="' . esc_attr( $lang ) . '"';
        if ( $highlight ) $data_attrs .= ' data-highlight="' . esc_attr( $highlight ) . '"';
        if ( $title )     $data_attrs .= ' data-title="' . esc_attr( $title ) . '"';
        if ( $collapse )  $data_attrs .= ' data-collapse="' . esc_attr( $collapse ) . '"';

        $code_class = $lang ? ' class="language-' . esc_attr( $lang ) . '"' : '';

        return '<pre class="lumicode-pre"' . $data_attrs . '><code' . $code_class . '>' . esc_html( $code ) . '</code></pre>';
    }
}
