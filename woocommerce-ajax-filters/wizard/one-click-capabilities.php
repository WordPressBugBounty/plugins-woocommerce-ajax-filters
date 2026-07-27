<?php
/**
 * License-aware feature map and recommended visual defaults for the one-click
 * setup.  Catalog analysis and UI code must use this class instead of reading
 * version-capability values or paid-only setting names directly.
 */
class BeRocket_AAPF_One_Click_Capabilities {
    const PRO_CAPABILITY = 10;
    const BUSINESS_CAPABILITY = 100;

    const CATEGORY_LARGE_TERM_THRESHOLD = 12;

    /**
     * Capability for mutations made by the one-click setup.
     *
     * The default is intentionally stricter than WooCommerce management: the
     * setup can add sidebar widgets and therefore changes theme presentation.
     * Hosts can map it to their own role capability without editing the plugin.
     */
    public static function get_setup_capability($context = 'setup') {
        $capability = apply_filters(
            'brapf_one_click_setup_capability',
            'edit_theme_options',
            sanitize_key($context)
        );
        return is_string($capability) && $capability ? $capability : 'edit_theme_options';
    }

    public static function current_user_can_manage_setup($context = 'setup') {
        return current_user_can(self::get_setup_capability($context));
    }

    /**
     * Return the active licence tier. Capability values are supplied by the
     * existing BeRocket licensing framework: Free < 10, Pro >= 10, Business >= 100.
     */
    public static function get_tier($capability = null) {
        if ($capability === null) {
            $capability = apply_filters('brfr_get_plugin_version_capability_ajax_filters', 0);
        }
        $capability = (int)$capability;
        if ($capability >= self::BUSINESS_CAPABILITY) {
            return 'business';
        }
        if ($capability >= self::PRO_CAPABILITY) {
            return 'pro';
        }
        return 'free';
    }

    public static function get_capability_value() {
        return (int)apply_filters('brfr_get_plugin_version_capability_ajax_filters', 0);
    }

    /**
     * The complete capability map for the active licence. Recommendation keys
     * are intentional product concepts; filter_type is the saved filter value.
     */
    public static function get_map($capability = null) {
        $tier = self::get_tier($capability);
        $map = array(
            'tier' => $tier,
            'filter_types' => array(
                'price' => array('filter_type' => 'price', 'available' => true),
                'categories' => array('filter_type' => 'all_product_cat', 'available' => true),
                'brand' => array(
                    'filter_type' => 'berocket_brand',
                    'available' => true,
                    'preferred_plugin' => 'brands-for-woocommerce/woocommerce-brand.php',
                    'supports_fallback_taxonomies' => true,
                ),
                'attribute' => array('filter_type' => 'attribute', 'available' => true),
                'rating' => array('filter_type' => '_rating', 'available' => true),
                'tags' => array('filter_type' => 'tag', 'available' => true),
                'availability' => array(
                    'filter_type' => '_stock_status',
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
                'on_sale' => array(
                    'filter_type' => '_sale',
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
            ),
            'features' => array(
                'groups' => array('available' => true),
                'price_new_slider' => array('available' => true),
                'category_hierarchy' => array('available' => true),
                'category_smooth_check' => array(
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
                'mobile_custom_sidebar' => array(
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
                'filters_above_products' => array(
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
                'inline_group_filters' => array(
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
                'title_only_group' => array(
                    'available' => false,
                    'minimum_tier' => 'pro',
                ),
                'ai_filter_generation' => array(
                    'available' => false,
                    'minimum_tier' => 'business',
                    'coming_soon' => true,
                ),
            ),
        );

        if ($tier === 'pro' || $tier === 'business') {
            $map['filter_types']['availability']['available'] = true;
            $map['filter_types']['on_sale']['available'] = true;
            $map['features']['mobile_custom_sidebar']['available'] = true;
            $map['features']['filters_above_products']['available'] = true;
            $map['features']['inline_group_filters']['available'] = true;
            $map['features']['title_only_group']['available'] = true;
            $map['features']['category_smooth_check']['available'] = true;
        }

        return apply_filters('brapf_one_click_capabilities', $map, $tier);
    }

    public static function supports($feature, $capability = null) {
        $map = self::get_map($capability);
        if (isset($map['features'][$feature])) {
            return !empty($map['features'][$feature]['available']);
        }
        if (isset($map['filter_types'][$feature])) {
            return !empty($map['filter_types'][$feature]['available']);
        }
        return false;
    }

    /**
     * BeRocket Brands is the preferred Brand provider. Checking the loaded
     * class first also works where WordPress does not load plugin.php.
     */
    public static function is_berocket_brands_active() {
        if (class_exists('BeRocket_product_brand')) {
            return true;
        }
        $plugin = 'brands-for-woocommerce/woocommerce-brand.php';
        if (function_exists('is_plugin_active') && is_plugin_active($plugin)) {
            return true;
        }
        return function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin);
    }

    /**
     * Structured UI data. Callers can render the translated message without
     * exposing a recommendation which cannot be created on this licence.
     */
    public static function get_feature_ui_state($feature, $capability = null) {
        $map = self::get_map($capability);
        $item = array();
        if (isset($map['features'][$feature])) {
            $item = $map['features'][$feature];
        } elseif (isset($map['filter_types'][$feature])) {
            $item = $map['filter_types'][$feature];
        }
        if (empty($item)) {
            return array(
                'available' => false,
                'message' => __('This setup option is not available.', 'BeRocket_AJAX_domain'),
            );
        }
        if (!empty($item['available'])) {
            return array('available' => true, 'message' => '');
        }
        if (!empty($item['coming_soon'])) {
            return array(
                'available' => false,
                'coming_soon' => true,
                'message' => __('AI filter generation is planned for Business.', 'BeRocket_AJAX_domain'),
            );
        }
        $tier = !empty($item['minimum_tier']) ? ucfirst($item['minimum_tier']) : __('a higher plan', 'BeRocket_AJAX_domain');
        return array(
            'available' => false,
            'minimum_tier' => !empty($item['minimum_tier']) ? $item['minimum_tier'] : '',
            'message' => sprintf(__('Available with %s.', 'BeRocket_AJAX_domain'), $tier),
        );
    }

    /**
     * Defaults for a filter definition. $context['category_count'] determines
     * whether category children are initially expanded or collapsed.
     */
    public static function get_filter_preset($recommendation, $context = array(), $capability = null) {
        if (!self::supports($recommendation, $capability)) {
            return false;
        }
        $presets = array(
            'price' => array(
                'widget_type' => 'filter',
                'filter_type' => 'price',
                'style' => 'new_slider',
            ),
            'categories' => array(
                'widget_type' => 'filter',
                'filter_type' => 'all_product_cat',
                'style' => self::supports('category_smooth_check', $capability) ? 'checkbox_smooth' : 'grey-check',
                'hide_child_attributes' => self::get_category_hierarchy_value($context),
            ),
            'availability' => array(
                'widget_type' => 'filter',
                'filter_type' => '_stock_status',
                'style' => 'grey-check',
            ),
            'on_sale' => array(
                'widget_type' => 'filter',
                'filter_type' => '_sale',
                'style' => 'grey-check',
            ),
        );
        return isset($presets[$recommendation]) ? $presets[$recommendation] : array();
    }

    /**
     * Settings for the two groups. The value append_group_id is an intentional
     * mutation descriptor: the setup orchestrator replaces it with the newly
     * created mobile group ID in br_filters_options[elements_above_products].
     */
    public static function get_group_preset($placement, $capability = null) {
        $preset = array(
            'group_settings' => array(),
            'option_mutations' => array(),
        );
        if ($placement === 'desktop') {
            $preset['group_settings']['hide_group'] = array(
                'mobile' => '1',
                'tablet' => '1',
            );
            return $preset;
        }
        if ($placement !== 'mobile') {
            return $preset;
        }

        $preset['group_settings']['hide_group'] = array('desktop' => '1');
        if (!self::supports('filters_above_products', $capability)) {
            return $preset;
        }
        $preset['placement'] = 'above_products';
        $preset['group_settings'] = array_merge($preset['group_settings'], array(
            'hidden_clickable' => '1',
            'title_only_theme' => '2',
        ));
        $preset['option_mutations'][] = array(
            'option' => 'br_filters_options',
            'path' => array('elements_above_products'),
            'operation' => 'append_group_id',
        );
        return $preset;
    }

    protected static function get_category_hierarchy_value($context) {
        $count = isset($context['category_count']) ? absint($context['category_count']) : 0;
        $threshold = (int)apply_filters(
            'brapf_one_click_category_large_term_threshold',
            self::CATEGORY_LARGE_TERM_THRESHOLD
        );
        return ($count >= max(2, $threshold)) ? '1' : '2';
    }
}
