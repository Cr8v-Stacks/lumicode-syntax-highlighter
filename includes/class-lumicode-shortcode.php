<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Shortcode {

    public static function init() {
        add_shortcode( 'lumicode', [ __CLASS__, 'render' ] );
        add_shortcode( 'code',     [ __CLASS__, 'render' ] );
    }

    public static function render( $atts, $content = '' ) {
        $atts = shortcode_atts( [
            'lang'           => '',
            'highlight'      => '',
            'title'          => '',
            'collapse'       => 'false',
            'collapse_after' => '', // per-block override; '' = use global setting
        ], $atts, 'lumicode' );

        $lang           = sanitize_text_field( $atts['lang'] );
        $highlight      = sanitize_text_field( $atts['highlight'] );
        $title          = sanitize_text_field( $atts['title'] );
        $collapse       = $atts['collapse'] === 'true' ? 'true' : 'false';
        $collapse_after = $atts['collapse_after'] !== '' ? absint( $atts['collapse_after'] ) : '';

        $content = trim( $content );
        // Decode HTML entities from TinyMCE Visual mode encoding
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        // Strip any <br> or <p> tags wpautop may have injected into the content
        $content = preg_replace( '#<br\s*/?>(\s*<br\s*/?>)*#i', "\n", $content );
        $content = wp_strip_all_tags( $content );

        $data_attrs  = '';
        if ( $lang )            $data_attrs .= ' data-lang="'           . esc_attr( $lang )           . '"';
        if ( $highlight )       $data_attrs .= ' data-highlight="'      . esc_attr( $highlight )      . '"';
        if ( $title )           $data_attrs .= ' data-title="'          . esc_attr( $title )          . '"';
        $data_attrs .= ' data-collapse="' . esc_attr( $collapse ) . '"';
        if ( $collapse_after !== '' ) $data_attrs .= ' data-collapse-after="' . esc_attr( $collapse_after ) . '"';

        $code_class = $lang ? ' class="language-' . esc_attr( $lang ) . '"' : '';

        /*
         * Wrap in a <div class="lumicode-outer"> — NOT just a bare <pre>.
         * wpautop never wraps a <div> in <p> tags, which permanently fixes
         * the "code pasting inline" issue regardless of content structure.
         */
        $html  = '<div class="lumicode-outer">';
        $html .= '<pre class="lumicode-pre"' . $data_attrs . '>';
        $html .= '<code' . $code_class . '>' . esc_html( $content ) . '</code>';
        $html .= '</pre>';
        $html .= '</div>';

        return $html;
    }
}
