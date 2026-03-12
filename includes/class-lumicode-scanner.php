<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LumiCode Scanner v1.4.3
 *
 * SAFETY:
 * - Never modifies content without explicit per-block user approval.
 * - Fingerprint uniquely identifies each raw block within its post.
 * - Apply uses wp_update_post() — fully reversible via post revisions.
 * - Dismissed blocks stored in user meta, invisible to future scans.
 *
 * FULL CODE CAPTURE:
 * - Returns the complete raw code text (inner content, tags stripped)
 *   as 'raw_code' so the scanner UI can display the full block.
 * - snippet is kept for backward compat but raw_code is preferred.
 */
class LumiCode_Scanner {

    const DISMISSED_META = 'lumicode_dismissed_blocks';

    /* ── Scan ──────────────────────────────────────────────── */
    public static function scan( $post_types = [ 'post', 'page' ], $limit = 300 ) {
        $results   = [];
        $dismissed = self::get_dismissed();

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
        ] );

        foreach ( $posts as $post_id ) {
            $post    = get_post( $post_id );
            $content = $post->post_content;

            // Match <pre> blocks NOT already wrapped by LumiCode
            preg_match_all(
                '/<pre(?![^>]*class=["\'][^"\']*lumicode)([^>]*)>([\s\S]*?)<\/pre>/i',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            if ( empty( $matches ) ) continue;

            foreach ( $matches as $m ) {
                $raw_block   = $m[0];
                // Full inner content with tags stripped — preserves newlines and whitespace
                $inner       = html_entity_decode( strip_tags( $m[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                $fingerprint = self::fingerprint( $post_id, $raw_block );

                $is_dismissed = isset( $dismissed[ $fingerprint ] );

                $results[] = [
                    'post_id'     => $post_id,
                    'post_title'  => get_the_title( $post_id ),
                    'edit_url'    => get_edit_post_link( $post_id ),
                    'post_url'    => get_permalink( $post_id ),
                    // raw_code = full code text, newlines preserved, no truncation
                    'raw_code'    => $inner,
                    // snippet kept for compat — summary of first 20 words
                    'snippet'     => self::clean_snippet( $inner, 20 ),
                    'lang'        => self::detect_language( $inner ),
                    'fingerprint' => $fingerprint,
                    // dismissed flag — JS renders these greyed but still shows them
                    'dismissed'   => $is_dismissed,
                ];
            }
        }

        return $results;
    }

    /* ── Apply single block ────────────────────────────────── */
    public static function apply_block( $post_id, $fingerprint, $lang = '' ) {
        $post = get_post( $post_id );
        if ( ! $post ) return false;

        $content = $post->post_content;
        $applied = false;

        $new_content = preg_replace_callback(
            '/<pre(?![^>]*class=["\'][^"\']*lumicode)([^>]*)>([\s\S]*?)<\/pre>/i',
            function ( $m ) use ( $post_id, $fingerprint, $lang, &$applied ) {
                if ( self::fingerprint( $post_id, $m[0] ) !== $fingerprint ) return $m[0];

                $attrs    = $m[1];
                $inner    = $m[2];
                $use_lang = $lang ?: self::detect_language(
                    html_entity_decode( strip_tags( $inner ), ENT_QUOTES | ENT_HTML5, 'UTF-8' )
                );
                $lang_attr = $use_lang ? ' data-lang="' . esc_attr( $use_lang ) . '"' : '';
                $applied   = true;
                // Preserve original inner content exactly — just add lumicode class + lang
                return '<pre' . $attrs . ' class="lumicode-pre"' . $lang_attr . '>' . $inner . '</pre>';
            },
            $content
        );

        if ( $applied && $new_content !== $content ) {
            wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ] );
            return true;
        }
        return false;
    }

    /* ── Dismiss ───────────────────────────────────────────── */
    public static function dismiss_block( $fingerprint ) {
        $d = self::get_dismissed();
        $d[ $fingerprint ] = time();
        update_user_meta( get_current_user_id(), self::DISMISSED_META, $d );
        return true;
    }

    /**
     * Returns genuine array of dismissed fingerprints.
     * (array)'' === [''] so we guard against empty meta explicitly.
     */
    public static function get_dismissed() {
        $raw = get_user_meta( get_current_user_id(), self::DISMISSED_META, true );
        if ( empty( $raw ) || ! is_array( $raw ) ) return [];
        return $raw;
    }

    public static function clear_dismissed() {
        delete_user_meta( get_current_user_id(), self::DISMISSED_META );
        return true;
    }

    /* ── Language detection ────────────────────────────────── */
    public static function detect_language( $code ) {
        $patterns = [
            'php'        => '/\<\?php|\becho\b\s+[\'"$]|\bfunction\b\s+\w+\s*\(|\$\w+\s*=/',
            'javascript' => '/\bconst\b\s+\w+\s*=|\blet\b\s+\w+|\b=>\s*[{\(]|\bconsole\.log\b/',
            'typescript' => '/:\s*(string|number|boolean|any|void|never)\b|interface\s+\w+\s*\{/',
            'python'     => '/\bdef\b\s+\w+\s*\(|^\s*import\s+\w+|^\s*from\s+\w+\s+import|\bprint\s*\(/m',
            'css'        => '/[\.\#][\w-]+\s*\{|@media\s+|@keyframes\s+|:\s*[\w#"\']+\s*;/',
            'html'       => '/<!DOCTYPE\s+html|<html[\s>]|<head[\s>]|<body[\s>]/i',
            'sql'        => '/\bSELECT\b.+\bFROM\b|\bINSERT\s+INTO\b|\bCREATE\s+TABLE\b/i',
            'bash'       => '/^#!\/(?:bin|usr)\/(?:env\s+)?bash|^\s*(?:export|echo|grep|sed|awk)\s+/m',
            'json'       => '/^\s*\{[\s\S]*"[\w-]+"\s*:\s*[\[{"\d]/',
            'go'         => '/\bpackage\s+main\b|\bfunc\s+\w+\s*\(|\bfmt\.Print/',
            'rust'       => '/\bfn\s+\w+\s*\(|\blet\s+mut\b|\bimpl\s+\w+/',
            'java'       => '/\bpublic\s+(?:static\s+)?(?:void|class|interface)\b|\bSystem\.out\.print/',
        ];
        foreach ( $patterns as $lang => $pattern ) {
            if ( preg_match( $pattern, $code ) ) return $lang;
        }
        return '';
    }

    /* ── Helpers ───────────────────────────────────────────── */
    public static function fingerprint( $post_id, $raw_block ) {
        return md5( $post_id . '||' . trim( $raw_block ) );
    }

    public static function clean_snippet( $text, $words = 20 ) {
        $text = preg_replace( '/\s+/', ' ', trim( $text ) );
        $arr  = explode( ' ', $text );
        if ( count( $arr ) > $words ) {
            return implode( ' ', array_slice( $arr, 0, $words ) ) . '…';
        }
        return $text;
    }

    /* ── AJAX ──────────────────────────────────────────────── */
    public static function ajax_scan() {
        check_ajax_referer( 'lumicode_scanner', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'lumicode' ), 403 );
        }
        wp_send_json_success( self::scan() );
    }

    public static function ajax_apply() {
        check_ajax_referer( 'lumicode_scanner', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'lumicode' ), 403 );
        }

        $post_id     = intval( $_POST['post_id']     ?? 0 );
        $fingerprint = sanitize_text_field( $_POST['fingerprint'] ?? '' );
        $lang        = sanitize_text_field( $_POST['lang']        ?? '' );

        if ( ! $post_id || ! $fingerprint ) {
            wp_send_json_error( __( 'Missing parameters', 'lumicode' ) );
        }
        wp_send_json_success( [ 'applied' => self::apply_block( $post_id, $fingerprint, $lang ) ] );
    }

    public static function ajax_dismiss() {
        check_ajax_referer( 'lumicode_scanner', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'lumicode' ), 403 );
        }

        $fingerprint = sanitize_text_field( $_POST['fingerprint'] ?? '' );
        if ( ! $fingerprint ) {
            wp_send_json_error( __( 'Missing fingerprint', 'lumicode' ) );
        }
        self::dismiss_block( $fingerprint );
        wp_send_json_success();
    }

    public static function ajax_clear_dismissed() {
        check_ajax_referer( 'lumicode_scanner', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'lumicode' ), 403 );
        }
        self::clear_dismissed();
        wp_send_json_success();
    }

    public static function init() {
        add_action( 'wp_ajax_lumicode_scan',            [ __CLASS__, 'ajax_scan' ] );
        add_action( 'wp_ajax_lumicode_apply',           [ __CLASS__, 'ajax_apply' ] );
        add_action( 'wp_ajax_lumicode_dismiss',         [ __CLASS__, 'ajax_dismiss' ] );
        add_action( 'wp_ajax_lumicode_clear_dismissed', [ __CLASS__, 'ajax_clear_dismissed' ] );
    }
}
