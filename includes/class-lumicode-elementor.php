<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Elementor {

    public static function init() {
        add_action( 'plugins_loaded', [ __CLASS__, 'on_plugins_loaded' ] );
    }

    public static function on_plugins_loaded() {
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widgets' ] );
    }

    public static function register_widgets( $widgets_manager ) {
        require_once LUMICODE_DIR . 'includes/class-lumicode-elementor-widget.php';
        $widgets_manager->register( new \LumiCode_Elementor_Widget() );
    }
}
LumiCode_Elementor::init();
