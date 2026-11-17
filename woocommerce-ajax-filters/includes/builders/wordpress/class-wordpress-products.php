<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'BeRocket_AAPF_Gutenberg_Products' ) ) {
    class BeRocket_AAPF_Gutenberg_Products {
        const EDITOR_SCRIPT_HANDLE = 'berocket-aapf-gutenberg-products-editor';
        const SETTING_ATTRIBUTE    = 'bapfApply';
        const APPLY_ATTRIBUTE     = 'bapfGutenbergApplyFilters';
        const PRODUCTS_CLASS      = 'bapf_products_gutenberg_apply_filters';
        const PAGINATION_CLASS    = 'bapf_pagination_gutenberg_apply_filters';

        protected $pending_apply = false;
        protected $pending_set = false;
        protected $filtering_depth = 0;
        protected $handled_marker_shortcodes = 0;

        protected $product_blocks = array(
            'woocommerce/product-collection',
            'woocommerce/product-query',
            'woocommerce/related-products',
            'woocommerce/product-new',
            'woocommerce/product-best-sellers',
            'woocommerce/product-on-sale',
            'woocommerce/product-top-rated',
            'woocommerce/product-category',
            'woocommerce/product-tag',
            'woocommerce/products-by-attribute',
            'woocommerce/handpicked-products',
        );

        public function __construct() {
            add_filter( 'register_block_type_args', array( $this, 'register_apply_attribute' ), 10, 2 );
            add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
            add_filter( 'aapf_localize_widget_script', array( $this, 'add_product_selectors' ) );
            add_filter( 'render_block_data', array( $this, 'prepare_render_block' ), 1, 3 );
            add_filter( 'render_block', array( $this, 'finish_render_block' ), 1000, 2 );
            add_filter( 'query_loop_block_query_vars', array( $this, 'apply_filters_to_query_loop' ), 1000, 3 );
            add_filter( 'woocommerce_blocks_product_grid_is_cacheable', array( $this, 'filter_product_grid_cache' ), 1000, 2 );
            add_action( 'brapf_next_shortcode_apply_action', array( $this, 'remember_apply_state' ), 20 );
            add_filter( 'pre_do_shortcode_tag', array( $this, 'suppress_handled_marker' ), 1, 4 );
        }

        public function register_apply_attribute( $args, $block_name ) {
            if ( ! $this->is_supported_block_name( $block_name ) ) {
                return $args;
            }

            if ( empty( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
                $args['attributes'] = array();
            }
            $args['attributes'][ self::SETTING_ATTRIBUTE ] = array(
                'type'    => 'string',
                'default' => 'default',
            );

            return $args;
        }

        public function enqueue_editor_assets() {
            if ( ! $this->supports_page_block_editor() || ! $this->can_manage_filters() ) {
                return;
            }

            $script_path = __DIR__ . '/assets/products.js';
            wp_enqueue_script(
                self::EDITOR_SCRIPT_HANDLE,
                plugins_url( 'assets/products.js', __FILE__ ),
                array( 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-element', 'wp-hooks', 'wp-i18n' ),
                file_exists( $script_path ) ? filemtime( $script_path ) : BeRocket_AJAX_filters_version,
                true
            );
            wp_set_script_translations(
                self::EDITOR_SCRIPT_HANDLE,
                'BeRocket_AJAX_domain',
                dirname( __DIR__, 3 ) . '/languages'
            );
        }

        public function add_product_selectors( $args ) {
            if ( ! $this->supports_page_block_editor() ) {
                return $args;
            }

            $product_selectors = array( '.' . self::PRODUCTS_CLASS );
            $pagination_selectors = array( '.' . self::PAGINATION_CLASS );

            $args['products_holder_id'] = $this->append_selectors(
                isset( $args['products_holder_id'] ) ? $args['products_holder_id'] : '',
                $product_selectors
            );
            $args['pagination_class'] = $this->append_selectors(
                isset( $args['pagination_class'] ) ? $args['pagination_class'] : '',
                $pagination_selectors
            );

            return $args;
        }

        public function prepare_render_block( $parsed_block, $source_block = null, $parent_block = null ) {
            if ( ! $this->supports_page_block_editor() ) {
                return $parsed_block;
            }

            if ( $this->is_shortcode_marker_block( $parsed_block ) ) {
                $this->prepare_shortcode_markers( $parsed_block['innerHTML'] );
            }

            if ( ! $this->is_products_block( $parsed_block ) ) {
                return $parsed_block;
            }

            $setting = $this->get_apply_setting( $parsed_block );
            $has_pending_state = $this->pending_set;
            $pending_apply = $this->pending_apply;
            $this->clear_pending_state();

            if ( 'enable' === $setting ) {
                $enabled = true;
                $set_query_marker = true;
            } elseif ( 'disable' === $setting ) {
                $enabled = false;
                $set_query_marker = true;
            } elseif ( $has_pending_state ) {
                $enabled = $pending_apply;
                $set_query_marker = false;
            } else {
                $enabled = $this->uses_page_query( $parsed_block );
                $set_query_marker = $enabled;
            }

            if ( $set_query_marker ) {
                $this->set_existing_query_marker( $enabled );
            }

            if ( empty( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
                $parsed_block['attrs'] = array();
            }
            $parsed_block['attrs'][ self::APPLY_ATTRIBUTE ] = $enabled;

            if ( $enabled ) {
                $this->filtering_depth++;
            }

            return $parsed_block;
        }

        public function finish_render_block( $block_content, $block ) {
            if ( empty( $block['attrs'] ) || ! array_key_exists( self::APPLY_ATTRIBUTE, $block['attrs'] ) ) {
                return $block_content;
            }

            if ( $block['attrs'][ self::APPLY_ATTRIBUTE ] ) {
                $block_content = $this->add_target_classes( $block_content );
                if ( $this->filtering_depth > 0 ) {
                    $this->filtering_depth--;
                }
            }

            return $block_content;
        }

        public function apply_filters_to_query_loop( $query_vars, $block, $page ) {
            if ( ! $this->is_filtering_block() || ! $this->is_product_query_vars( $query_vars ) ) {
                return $query_vars;
            }

            $query_vars['bapf_apply'] = true;
            $query_vars['bapf_save_query'] = true;

            return apply_filters( 'bapf_uparse_apply_filters_to_query_vars_save', $query_vars );
        }

        public function filter_product_grid_cache( $is_cacheable, $query_args = array() ) {
            if ( $this->is_filtering_block() && $this->has_selected_filters() ) {
                return false;
            }

            return $is_cacheable;
        }

        public function remember_apply_state( $atts = array() ) {
            if ( ! is_array( $atts ) ) {
                $atts = array();
            }
            $atts = array_merge( array( 'apply' => true ), $atts );

            if ( 'default' === $atts['apply'] ) {
                $this->pending_set = false;
                $this->pending_apply = false;
                return;
            }

            $this->pending_set = true;
            $this->pending_apply = ! ( false === $atts['apply'] || 'false' === $atts['apply'] );
        }

        public function suppress_handled_marker( $return, $tag, $attr, $match ) {
            if ( 'brapf_next_shortcode_apply' !== $tag || $this->handled_marker_shortcodes <= 0 ) {
                return $return;
            }

            $this->handled_marker_shortcodes--;
            return '';
        }

        protected function prepare_shortcode_markers( $content ) {
            if ( false === strpos( $content, '[brapf_next_shortcode_apply' ) ) {
                return;
            }

            $pattern = get_shortcode_regex( array( 'brapf_next_shortcode_apply' ) );
            if ( ! preg_match_all( '/' . $pattern . '/', $content, $matches, PREG_SET_ORDER ) ) {
                return;
            }

            foreach ( $matches as $match ) {
                $atts = shortcode_parse_atts( $match[3] );
                if ( ! is_array( $atts ) ) {
                    $atts = array();
                }
                $this->handled_marker_shortcodes++;
                do_action( 'brapf_next_shortcode_apply_action', $atts );
            }
        }

        protected function is_shortcode_marker_block( $block ) {
            return isset( $block['blockName'], $block['innerHTML'] )
                && 'core/shortcode' === $block['blockName']
                && false !== strpos( $block['innerHTML'], '[brapf_next_shortcode_apply' );
        }

        protected function is_products_block( $block ) {
            if ( empty( $block['blockName'] ) ) {
                return false;
            }
            if ( $this->is_supported_block_name( $block['blockName'] ) && 'core/query' !== $block['blockName'] ) {
                return true;
            }
            if ( 'core/query' !== $block['blockName'] ) {
                return false;
            }

            $post_type = isset( $block['attrs']['query']['postType'] ) ? $block['attrs']['query']['postType'] : '';
            return $this->is_product_post_type( $post_type );
        }

        protected function is_supported_block_name( $block_name ) {
            return 'core/query' === $block_name || in_array( $block_name, $this->product_blocks, true );
        }

        protected function get_apply_setting( $block ) {
            $setting = isset( $block['attrs'][ self::SETTING_ATTRIBUTE ] ) ? $block['attrs'][ self::SETTING_ATTRIBUTE ] : 'default';
            return in_array( $setting, array( 'default', 'enable', 'disable' ), true ) ? $setting : 'default';
        }

        protected function uses_page_query( $block ) {
            return ! empty( $block['attrs']['query']['inherit'] );
        }

        protected function set_existing_query_marker( $enabled ) {
            do_action( 'brapf_next_shortcode_apply_action', array( 'apply' => (bool) $enabled ) );
            $this->clear_pending_state();
        }

        protected function clear_pending_state() {
            $this->pending_set = false;
            $this->pending_apply = false;
        }

        protected function is_product_query_vars( $query_vars ) {
            $post_type = isset( $query_vars['post_type'] ) ? $query_vars['post_type'] : '';
            return $this->is_product_post_type( $post_type );
        }

        protected function is_product_post_type( $post_type ) {
            if ( is_array( $post_type ) ) {
                return 1 === count( $post_type ) && 'product' === reset( $post_type );
            }
            return 'product' === $post_type;
        }

        protected function is_filtering_block() {
            return $this->filtering_depth > 0;
        }

        protected function has_selected_filters() {
            global $berocket_parse_page_obj;

            if ( ! is_object( $berocket_parse_page_obj ) || ! method_exists( $berocket_parse_page_obj, 'get_current' ) ) {
                return false;
            }

            $data = $berocket_parse_page_obj->get_current();
            if ( ! is_array( $data ) ) {
                return false;
            }

            foreach ( array( 'filters', 'tax_query', 'meta_query', 'post__in', 'post__not_in' ) as $key ) {
                if ( ! empty( $data[ $key ] ) ) {
                    return true;
                }
            }

            return false;
        }

        protected function supports_page_block_editor() {
            return function_exists( 'use_block_editor_for_post_type' )
                && use_block_editor_for_post_type( 'page' );
        }

        protected function can_manage_filters() {
            return (bool) apply_filters( 'bapf_gutenberg_blocks_user_can_use', current_user_can( 'manage_woocommerce' ) );
        }

        protected function append_selectors( $current, $selectors ) {
            $current_selectors = array_filter( array_map( 'trim', explode( ',', (string) $current ) ) );
            $selectors = array_unique( array_merge( $current_selectors, $selectors ) );
            return implode( ', ', $selectors );
        }

        protected function add_target_classes( $html ) {
            if ( '' === trim( $html ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
                return $html;
            }

            $product_classes = array(
                'wc-block-product-template',
                'wp-block-woocommerce-product-template',
                'wc-block-grid__products',
                'wp-block-post-template',
                'products',
            );
            $pagination_classes = array(
                'wp-block-query-pagination',
                'wc-block-pagination',
                'woocommerce-pagination',
            );
            $processor = new WP_HTML_Tag_Processor( $html );

            while ( $processor->next_tag() ) {
                foreach ( $product_classes as $class_name ) {
                    if ( $processor->has_class( $class_name ) ) {
                        $processor->add_class( self::PRODUCTS_CLASS );
                        break;
                    }
                }
                foreach ( $pagination_classes as $class_name ) {
                    if ( $processor->has_class( $class_name ) ) {
                        $processor->add_class( self::PAGINATION_CLASS );
                        break;
                    }
                }
            }

            return $processor->get_updated_html();
        }
    }

    new BeRocket_AAPF_Gutenberg_Products();
}
