<?php
/**
 * Plugin Name: LumiCode Syntax Highlighter
 * Plugin URI: https://cr8vstacks.com/dev-playground/lumicode-syntax-highlighter/
 * Description: Beautiful syntax highlighting with auto-detection, copy buttons, line numbers, and a safe scanner.
 * Version: 1.5.8
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Cr8v Stacks
 * Author URI: https://cr8vstacks.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lumicode-syntax-highlighter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LUMICODE_VERSION', '1.5.8' );
define( 'LUMICODE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LUMICODE_URL',     plugin_dir_url( __FILE__ ) );

require_once LUMICODE_DIR . 'includes/class-lumicode-settings.php';
require_once LUMICODE_DIR . 'includes/class-lumicode-frontend.php';
require_once LUMICODE_DIR . 'includes/class-lumicode-scanner.php';
require_once LUMICODE_DIR . 'includes/class-lumicode-shortcode.php';
require_once LUMICODE_DIR . 'includes/class-lumicode-block.php';
require_once LUMICODE_DIR . 'includes/class-lumicode-tinymce.php';
require_once LUMICODE_DIR . 'admin/class-lumicode-admin.php';

register_activation_hook( __FILE__, 'lumicode_activate' );

function lumicode_activate() {
    $defaults = [
        'theme'          => 'atom-one-dark',
        'line_numbers'   => true,
        'copy_button'    => true,
        'language_badge' => true,
        'font_size'      => '14',
        'font_family'    => 'JetBrains Mono, Fira Code, monospace',
        'auto_detect'    => true,
        'collapse_after' => 30,
        'light_mode'     => false,
    ];
    add_option( 'lumicode_settings', $defaults );
}

LumiCode_Settings::init();
LumiCode_Frontend::init();
LumiCode_Shortcode::init();
LumiCode_Block::init();
LumiCode_TinyMCE::init();
LumiCode_Scanner::init();
if ( is_admin() ) {
    LumiCode_Admin::init();
}

add_filter( 'the_content', 'do_shortcode',     8 );
add_filter( 'the_content', 'shortcode_unautop', 9 );
