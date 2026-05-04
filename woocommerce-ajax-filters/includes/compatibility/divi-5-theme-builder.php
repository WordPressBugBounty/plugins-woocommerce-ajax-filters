<?php
if( ! class_exists('BeRocket_AAPF_compat_Divi_5_theme_builder') ) {
    class BeRocket_AAPF_compat_Divi_5_theme_builder {
        public $attributes;
        public $attributes_stack = array();
        public $filtering_depth = 0;
        public $module_names = array(
            'divi/shop',
            'divi/woocommerce-related-products',
            'divi.shop',
            'divi.woocommerce-related-products',
        );
        function __construct() {
            add_action('divi_visual_builder_assets_before_enqueue_scripts', array($this, 'enqueue_builder_script'));
            add_filter('block_type_metadata_settings', array($this, 'add_field_metadata'));
            add_filter('render_block_data', array($this, 'prepare_render_block'), 1, 3);
            add_filter('render_block', array($this, 'render_block'), 1000, 2);
            add_filter('bapf_isoption_ajax_site', array($this, 'enable_for_builder'));
            add_filter('aapf_localize_widget_script', array($this, 'modify_products_selector'));
            if( defined('DOING_AJAX') && in_array(berocket_isset($_REQUEST['action']), array('brapf_get_single_filter', 'brapf_get_group_filter')) ) {
                add_filter('braapf_check_widget_by_instance_single', array($this, 'disable_conditions'));
                add_filter('braapf_check_widget_by_instance_group', array($this, 'disable_conditions'));
            }
            if( $this->is_builder_request() ) {
                add_action('wp_footer', array($this, 'apply_styles'));
            }
        }
        function enqueue_builder_script() {
            if( ! function_exists('et_builder_d5_enabled') || ! et_builder_d5_enabled() || ! function_exists('et_core_is_fb_enabled') || ! et_core_is_fb_enabled() ) {
                return;
            }
            if( ! class_exists('\ET\Builder\VisualBuilder\Assets\PackageBuildManager') ) {
                return;
            }
            \ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
                array(
                    'name'    => 'bapf-divi-5-theme-builder',
                    'version' => BeRocket_AJAX_filters_version,
                    'script'  => array(
                        'src'                => plugins_url('assets/admin/js/divi-5-theme-builder.js', BeRocket_AJAX_filters_file),
                        'deps'               => array(
                            'lodash',
                            'divi-vendor-wp-hooks',
                        ),
                        'enqueue_top_window' => false,
                        'enqueue_app_window' => true,
                        'args'               => array(
                            'in_footer' => false,
                        ),
                    ),
                )
            );
        }
        function modify_products_selector($args) {
            if( ! empty($args['products_holder_id']) ) {
                $args['products_holder_id'] .= ',';
            }
            $args['products_holder_id'] .= '.bapf_products_divi5_apply_filters .products';
            if( ! empty($args['pagination_class']) ) {
                $args['pagination_class'] .= ',';
            }
            $args['pagination_class'] .= '.bapf_products_divi5_apply_filters .woocommerce-pagination';
            return $args;
        }
        function add_field_metadata($settings) {
            if( empty($settings['name']) || ! $this->is_products_module_name($settings['name'], $settings) ) {
                return $settings;
            }
            if( empty($settings['attributes']) || ! is_array($settings['attributes']) ) {
                $settings['attributes'] = array();
            }
            if( empty($settings['attributes']['content']) || ! is_array($settings['attributes']['content']) ) {
                $settings['attributes']['content'] = array(
                    'type'     => 'object',
                    'settings' => array(),
                );
            }
            if( empty($settings['attributes']['content']['settings']) || ! is_array($settings['attributes']['content']['settings']) ) {
                $settings['attributes']['content']['settings'] = array();
            }
            if( empty($settings['attributes']['content']['settings']['advanced']) || ! is_array($settings['attributes']['content']['settings']['advanced']) ) {
                $settings['attributes']['content']['settings']['advanced'] = array();
            }
            $settings['attributes']['content']['settings']['advanced']['bapfApply'] = array(
                'groupType' => 'group-item',
                'item'      => $this->get_field_item_metadata($this->get_group_slug($settings)),
            );
            return $settings;
        }
        function get_field_item_metadata($group_slug = 'contentMainContent', $attr_name = 'content.advanced.bapfApply') {
            return array(
                'attrName'    => $attr_name,
                'label'       => esc_html__('Apply BeRocket AJAX Filters', 'BeRocket_AJAX_domain'),
                'description' => esc_html__('All Filters will be applied to this module. You need correct unique selectors to work correct', 'BeRocket_AJAX_domain'),
                'groupSlug'   => $group_slug,
                'priority'    => 99,
                'render'      => true,
                'defaultAttr' => array(
                    'desktop' => array(
                        'value' => 'default',
                    ),
                ),
                'features'    => array(
                    'hover'      => false,
                    'sticky'     => false,
                    'responsive' => false,
                    'preset'     => 'content',
                ),
                'component'   => array(
                    'type'  => 'field',
                    'name'  => 'divi/select',
                    'props' => array(
                        'defaultValue' => 'default',
                        'options' => array(
                            'default' => array(
                                'label' => esc_html__('Default', 'BeRocket_AJAX_domain'),
                            ),
                            'enable' => array(
                                'label' => esc_html__('Enable', 'BeRocket_AJAX_domain'),
                            ),
                            'disable' => array(
                                'label' => esc_html__('Disable', 'BeRocket_AJAX_domain'),
                            ),
                        ),
                    ),
                ),
            );
        }
        function get_field_metadata($group_slug = 'contentMainContent') {
            return array(
                'type'     => 'object',
                'settings' => array(
                    'innerContent' => array(
                        'groupType' => 'group-item',
                        'item'      => $this->get_field_item_metadata($group_slug, 'bapfApply.innerContent'),
                    ),
                ),
                'default'  => array(
                    'innerContent' => 'default',
                ),
            );
        }
        function get_group_slug($settings) {
            $groups = empty($settings['settings']['groups']) || ! is_array($settings['settings']['groups']) ? array() : $settings['settings']['groups'];
            foreach( array('contentMainContent', 'contentContent', 'content', 'mainContent', 'main_content') as $group_slug ) {
                if( isset($groups[$group_slug]) ) {
                    return $group_slug;
                }
            }
            foreach( $groups as $group_slug => $group ) {
                $panel = '';
                if( is_array($group) ) {
                    $panel = br_get_value_from_array($group, array('panel'));
                    if( empty($panel) ) {
                        $panel = br_get_value_from_array($group, array('panelName'));
                    }
                }
                if( strpos($group_slug, 'content') !== false || $panel == 'content' ) {
                    return $group_slug;
                }
            }
            return 'content';
        }
        function prepare_render_block($parsed_block, $source_block = null, $parent_block = null) {
            if( empty($parsed_block['blockName']) || ! $this->is_products_module_name($parsed_block['blockName']) ) {
                return $parsed_block;
            }
            $this->attributes = empty($parsed_block['attrs']) || ! is_array($parsed_block['attrs']) ? array() : $parsed_block['attrs'];
            $this->attributes_stack[] = $this->attributes;
            if( $this->filtering_depth <= 0 ) {
                add_filter('berocket_aapf_wcshortcode_is_filtering', array($this, 'enable_filtering'), 1000);
                add_filter('woocommerce_related_products', array($this, 'filter_related_products'), 1000, 3);
            }
            $this->filtering_depth++;
            return $parsed_block;
        }
        function render_block($block_content, $block) {
            if( empty($block['blockName']) || ! $this->is_products_module_name($block['blockName']) ) {
                return $block_content;
            }
            $this->attributes = empty($this->attributes_stack) ? (empty($block['attrs']) || ! is_array($block['attrs']) ? array() : $block['attrs']) : array_pop($this->attributes_stack);
            $enabled = $this->is_filtering_enabled();
            if( $enabled ) {
                $block_content = $this->add_class_to_html($block_content, 'bapf_products_divi5_apply_filters');
            }
            $this->cleanup_render_block();
            return $block_content;
        }
        function cleanup_render_block() {
            if( $this->filtering_depth > 0 ) {
                $this->filtering_depth--;
            }
            if( $this->filtering_depth <= 0 ) {
                $this->filtering_depth = 0;
                remove_filter('berocket_aapf_wcshortcode_is_filtering', array($this, 'enable_filtering'), 1000);
                remove_filter('woocommerce_related_products', array($this, 'filter_related_products'), 1000);
            }
            $this->attributes = empty($this->attributes_stack) ? array() : end($this->attributes_stack);
        }
        function enable_filtering($enabled) {
            return $this->is_filtering_enabled();
        }
        function filter_related_products($related_posts, $product_id, $args = array()) {
            if( empty($related_posts) || ! is_array($related_posts) || ! $this->is_filtering_enabled() ) {
                return $related_posts;
            }
            global $berocket_parse_page_obj;
            if( ! is_object($berocket_parse_page_obj) || ! method_exists($berocket_parse_page_obj, 'query_vars_apply_filters') ) {
                return $related_posts;
            }
            $query_vars = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__in'       => array_map('absint', $related_posts),
                'orderby'        => 'post__in',
            );
            $query_vars = $berocket_parse_page_obj->query_vars_apply_filters($query_vars);
            $filtered_posts = get_posts($query_vars);
            if( ! is_array($filtered_posts) ) {
                return $related_posts;
            }
            $filtered_posts = array_map('absint', $filtered_posts);
            return array_values(array_filter($related_posts, function($related_post_id) use ($filtered_posts) {
                return in_array(absint($related_post_id), $filtered_posts);
            }));
        }
        function is_filtering_enabled() {
            $enabled = braapf_is_shortcode_must_be_filtered();
            $bapf_apply = $this->get_attribute_path_value($this->attributes, array('content', 'advanced', 'bapfApply'), 'default');
            if( $bapf_apply == 'default' ) {
                $bapf_apply = $this->get_attribute_value($this->attributes, 'bapfApply', 'default');
            }
            if( $bapf_apply == 'enable' ) {
                $enabled = true;
            } elseif( $bapf_apply == 'disable' ) {
                $enabled = false;
            } elseif( $this->get_attribute_path_value($this->attributes, array('content', 'advanced', 'useCurrentLoop'), 'off') == 'on' && ( is_post_type_archive('product') || is_search() || et_is_product_taxonomy() ) ) {
                $enabled = true;
            }
            return $enabled;
        }
        function get_attribute_path_value($attributes, $path, $default = '') {
            $value = $attributes;
            foreach( $path as $path_part ) {
                if( ! is_array($value) || ! array_key_exists($path_part, $value) ) {
                    return $default;
                }
                $value = $value[$path_part];
            }
            return $this->normalize_attribute_value($value, $default);
        }
        function get_attribute_value($attributes, $name, $default = '') {
            if( empty($attributes[$name]) ) {
                return $default;
            }
            return $this->normalize_attribute_value($attributes[$name], $default);
        }
        function normalize_attribute_value($value, $default = '') {
            if( is_array($value) && isset($value['innerContent']) ) {
                $value = $value['innerContent'];
            }
            if( is_array($value) && isset($value['desktop']['value']) ) {
                $value = $value['desktop']['value'];
            }
            if( is_array($value) && isset($value['value']) ) {
                $value = $value['value'];
            }
            return is_string($value) ? $value : $default;
        }
        function add_class_to_html($html, $class) {
            if( strpos($html, $class) !== false ) {
                return $html;
            }
            if( preg_match('/class=(["\'])([^"\']*)\1/', $html) ) {
                return preg_replace('/class=(["\'])([^"\']*)\1/', 'class=$1$2 ' . $class . '$1', $html, 1);
            }
            return preg_replace('/^<([a-z0-9:-]+)/i', '<$1 class="' . esc_attr($class) . '"', $html, 1);
        }
        function is_products_module_name($name, $settings = array()) {
            if( in_array($name, $this->module_names) ) {
                return true;
            }
            $name_normalized = str_replace('_', '-', $name);
            if( ! empty($settings['title']) && preg_match('/^Woo Products$/i', $settings['title']) ) {
                return true;
            }
            if( ! empty($settings['title']) && preg_match('/^Woo Related Products$/i', $settings['title']) ) {
                return true;
            }
            return false;
        }
        function disable_conditions($return) {
            return false;
        }
        function enable_for_builder($enabled) {
            if( $this->is_builder_request() ) {
                $enabled = true;
            }
            return $enabled;
        }
        function is_builder_request() {
            return br_get_value_from_array($_GET, 'et_fb') == 1
                || (defined('DOING_AJAX') && in_array(berocket_isset($_REQUEST['action']), array('brapf_get_single_filter', 'brapf_get_group_filter')))
                || (! empty($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'et_body_layout');
        }
        function apply_styles() {
            ?>
            <script>
            jQuery(document).ajaxComplete( function() {
                setTimeout(braapf_init_load, 100);
            });
            </script>
            <?php
        }
    }
    new BeRocket_AAPF_compat_Divi_5_theme_builder();
}
