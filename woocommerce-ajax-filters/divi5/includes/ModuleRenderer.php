<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BAPF_Divi5_Module_Renderer {
    protected $module_name;
    protected $module_type;
    protected $defaults;
    protected $content;
    protected $current_atts = array();

    public function __construct( $args ) {
        $this->module_name = $args['name'];
        $this->module_type = $args['type'];
        $this->defaults    = $args['defaults'];
    }

    public function render_module( $attrs, $content = '' ) {
        $this->content = $content;
        $atts          = $this->attrs_to_atts( $attrs );
        $this->current_atts = $atts;

        if ( 'single' === $this->module_type ) {
            return $this->render_single_filter( $atts );
        }

        if ( 'group' === $this->module_type ) {
            return $this->render_filters_group( $atts );
        }

        if ( 'filter_next' === $this->module_type ) {
            add_filter( 'berocket_aapf_wcshortcode_is_filtering', array( $this, 'enable_filtering' ) );
        } elseif ( 'group_item' === $this->module_type ) {
            $filter_id = empty( $atts['filter_id'] ) ? 0 : $this->parse_id( $atts['filter_id'] );
            return empty( $filter_id ) ? '' : esc_html( $filter_id ) . ',';
        } elseif ( 'sidebar_button' === $this->module_type ) {
            return $this->render_sidebar_button( $atts );
        }

        return '';
    }

    public function attrs_to_atts( $attrs ) {
        $atts = $this->defaults;

        foreach ( array_keys( $this->defaults ) as $key ) {
            $value = $this->get_attr_value( $attrs, $key );
            if ( null !== $value ) {
                $atts[ $key ] = $value;
            }
        }

        if ( 'group' === $this->module_type ) {
            $filters = $this->get_attr_value( $attrs, 'filters' );
            if ( null !== $filters ) {
                $atts['filters'] = $filters;
            }
        }

        return BAPF_Divi5_Module_Renderer::convert_on_off( $atts );
    }

    public function enable_filtering() {
        remove_filter( 'berocket_aapf_wcshortcode_is_filtering', array( $this, 'enable_filtering' ) );
        return true;
    }

    protected function render_single_filter( $atts ) {
        $filter_id = empty( $atts['filter_id'] ) ? '' : $this->parse_id( $atts['filter_id'] );
        if ( ! $filter_id && ! empty( $this->defaults['filter_id'] ) ) {
            $filter_id = $this->parse_id( $this->defaults['filter_id'] );
        }

        add_filter( 'BeRocket_AAPF_template_full_content', array( $this, 'header_replace' ), 4000, 1 );
        add_filter( 'BeRocket_AAPF_template_full_element_content', array( $this, 'header_replace' ), 4000, 1 );
        $html = $filter_id ? trim( do_shortcode( '[br_filter_single filter_id=' . $filter_id . ']' ) ) : '';
        remove_filter( 'BeRocket_AAPF_template_full_content', array( $this, 'header_replace' ), 4000, 1 );
        remove_filter( 'BeRocket_AAPF_template_full_element_content', array( $this, 'header_replace' ), 4000, 1 );

        return $html;
    }

    protected function render_filters_group( $atts ) {
        $args = array();

        if ( empty( $atts['group_id'] ) ) {
            foreach ( array( 'display_inline', 'display_inline_count', 'min_filter_width_inline', 'hidden_clickable', 'hidden_clickable_hover', 'group_is_hide' ) as $option ) {
                $args[ $option ] = empty( $atts[ $option ] ) ? '' : $atts[ $option ];
            }

            $args['filters'] = $this->get_group_filter_ids( $atts );
        } else {
            $args['group_id'] = absint( $atts['group_id'] );
        }

        add_filter( 'BeRocket_AAPF_template_full_content', array( $this, 'header_replace' ), 4000, 1 );
        add_filter( 'BeRocket_AAPF_template_full_element_content', array( $this, 'header_replace' ), 4000, 1 );
        ob_start();
        the_widget( 'BeRocket_new_AAPF_Widget', $args );
        $html = ob_get_clean();
        remove_filter( 'BeRocket_AAPF_template_full_content', array( $this, 'header_replace' ), 4000, 1 );
        remove_filter( 'BeRocket_AAPF_template_full_element_content', array( $this, 'header_replace' ), 4000, 1 );

        return $html;
    }

    protected function get_group_filter_ids( $atts ) {
        $filters = array();
        $source  = empty( $atts['filters'] ) ? $this->content : $atts['filters'];

        if ( is_string( $source ) && preg_match_all( '/(?:ID:)?\s*(\d+)/', $source, $matches ) ) {
            $filters = array_map( array( $this, 'parse_id' ), $matches[1] );
        } elseif ( is_array( $source ) ) {
            $filters = array_map( array( $this, 'parse_id' ), $source );
        }

        return array_values( array_filter( $filters ) );
    }

    public function parse_id( $value ) {
        if ( is_numeric( $value ) ) {
            return absint( $value );
        }

        if ( is_string( $value ) && preg_match( '/(?:ID:)?\s*(\d+)/', $value, $matches ) ) {
            return absint( $matches[1] );
        }

        return 0;
    }

    protected function render_sidebar_button( $atts ) {
        if ( ! is_active_sidebar( 'berocket-ajax-filters' ) ) {
            return '';
        }

        ob_start();
        do_action( 'braapf_sidebar_button_show', $atts );
        return ob_get_clean();
    }

    protected function get_attr_value( $attrs, $key ) {
        if ( isset( $attrs[ $key ]['innerContent']['desktop']['value'] ) ) {
            return $this->normalize_attr_value( $attrs[ $key ]['innerContent']['desktop']['value'] );
        }

        if ( 'filters' === $key && isset( $attrs[ $key ] ) && is_array( $attrs[ $key ] ) ) {
            return $attrs[ $key ];
        }

        if ( isset( $attrs[ $key ] ) && ! is_array( $attrs[ $key ] ) ) {
            return $attrs[ $key ];
        }

        return null;
    }

    protected function normalize_attr_value( $value ) {
        if ( is_array( $value ) ) {
            foreach ( array( 'value', 'number', 'amount' ) as $value_key ) {
                if ( isset( $value[ $value_key ] ) && '' !== $value[ $value_key ] && ! is_array( $value[ $value_key ] ) ) {
                    return $value[ $value_key ];
                }
            }

            return '';
        }

        return $value;
    }

    public static function convert_on_off( $atts ) {
        foreach ( $atts as &$attr ) {
            if ( 'on' === $attr || 'off' === $attr ) {
                $attr = ( 'on' === $attr );
            }
        }

        return $atts;
    }

    public function header_replace( $template_content ) {
        $atts = $this->current_atts;
        if ( ! empty( $atts['title_level'] ) ) {
            $template_content['template']['content']['header']['content']['title']['tag'] = $atts['title_level'];
        }

        return $template_content;
    }
}
