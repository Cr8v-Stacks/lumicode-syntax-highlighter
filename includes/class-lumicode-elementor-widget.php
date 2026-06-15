<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LumiCode_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'lumicode-code-block';
    }

    public function get_title() {
        return esc_html__( 'LumiCode Code Block', 'lumicode-syntax-highlighter' );
    }

    public function get_icon() {
        return 'eicon-code';
    }

    public function get_categories() {
        return [ 'general' ];
    }

    public function get_keywords() {
        return [ 'code', 'syntax', 'highlight', 'pre', 'developer', 'lumicode', 'elementor' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Code Content', 'lumicode-syntax-highlighter' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'language',
            [
                'label'   => esc_html__( 'Language', 'lumicode-syntax-highlighter' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'javascript',
                'options' => [
                    ''                 => esc_html__( 'Auto Detect', 'lumicode-syntax-highlighter' ),
                    'javascript'       => 'JavaScript',
                    'typescript'       => 'TypeScript',
                    'php'              => 'PHP',
                    'html'             => 'HTML',
                    'css'              => 'CSS',
                    'python'           => 'Python',
                    'go'               => 'Go',
                    'rust'             => 'Rust',
                    'sql'              => 'SQL',
                    'yaml'             => 'YAML',
                    'json'             => 'JSON',
                    'bash'             => 'Bash / Shell',
                    'java'             => 'Java',
                    'cpp'              => 'C++',
                    'csharp'           => 'C#',
                    'ruby'             => 'Ruby',
                    'xml'              => 'XML',
                    'markdown'         => 'Markdown',
                    'php-template'     => 'PHP Template',
                ],
            ]
        );

        $this->add_control(
            'code',
            [
                'label'       => esc_html__( 'Code', 'lumicode-syntax-highlighter' ),
                'type'        => \Elementor\Controls_Manager::CODE,
                'language'    => 'javascript',
                'rows'        => 12,
                'default'     => 'console.log("Hello LumiCode!");',
                'placeholder' => esc_html__( 'Paste or type your code here...', 'lumicode-syntax-highlighter' ),
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => esc_html__( 'Title / Filename', 'lumicode-syntax-highlighter' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'e.g. index.js (optional)', 'lumicode-syntax-highlighter' ),
            ]
        );

        $this->add_control(
            'highlight',
            [
                'label'       => esc_html__( 'Highlight Lines', 'lumicode-syntax-highlighter' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'e.g. 2,4-6 (optional)', 'lumicode-syntax-highlighter' ),
            ]
        );

        $this->add_control(
            'line_numbers',
            [
                'label'     => esc_html__( 'Show Line Numbers', 'lumicode-syntax-highlighter' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => 'default',
                'options'   => [
                    'default' => esc_html__( 'Global Default', 'lumicode-syntax-highlighter' ),
                    'true'    => esc_html__( 'Yes', 'lumicode-syntax-highlighter' ),
                    'false'   => esc_html__( 'No', 'lumicode-syntax-highlighter' ),
                ],
            ]
        );

        $this->add_control(
            'collapse',
            [
                'label'     => esc_html__( 'Force Collapsible', 'lumicode-syntax-highlighter' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => 'default',
                'options'   => [
                    'default' => esc_html__( 'Global Default', 'lumicode-syntax-highlighter' ),
                    'true'    => esc_html__( 'Yes', 'lumicode-syntax-highlighter' ),
                    'false'   => esc_html__( 'No', 'lumicode-syntax-highlighter' ),
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $code     = $settings['code'];
        $lang     = preg_replace( '/[^a-z0-9_-]/i', '', sanitize_text_field( $settings['language'] ) );
        $title    = sanitize_text_field( $settings['title'] );
        $highlight = sanitize_text_field( $settings['highlight'] );
        
        $data_attrs = '';
        if ( $lang ) {
            $data_attrs .= ' data-lang="' . esc_attr( $lang ) . '"';
        }
        if ( $title ) {
            $data_attrs .= ' data-title="' . esc_attr( $title ) . '"';
        }
        if ( $highlight ) {
            $data_attrs .= ' data-highlight="' . esc_attr( $highlight ) . '"';
        }
        if ( $settings['line_numbers'] !== 'default' ) {
            $data_attrs .= ' data-line-numbers="' . esc_attr( $settings['line_numbers'] ) . '"';
        }
        if ( $settings['collapse'] !== 'default' ) {
            $data_attrs .= ' data-collapse="' . esc_attr( $settings['collapse'] ) . '"';
        }

        $code_class = $lang ? ' class="language-' . esc_attr( $lang ) . '"' : '';

        echo '<pre class="lumicode-pre"' . $data_attrs . '><code' . $code_class . '>' . esc_html( $code ) . '</code></pre>';
    }
}
