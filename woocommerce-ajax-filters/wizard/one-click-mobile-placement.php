<?php
/**
 * Creates and places the mobile presentation of a one-click filter setup.
 *
 * A group only references filter post IDs, so the desktop and mobile groups
 * intentionally share definitions while retaining independent display data.
 */
class BeRocket_AAPF_One_Click_Mobile_Placement {
    const SIDEBAR_ID = 'berocket-ajax-filters';
    const WIDGET_ID_BASE = 'berocket_aapf_group';

    /**
     * Return the placement that an orchestrator must use. Pro/Business uses
     * the compact above-products control requested for the mobile preset. A
     * caller can request custom_sidebar where the slide-out is preferred.
     */
    public function resolve($filter_ids, $capability = null, $strategy = '', $desktop_plan = array()) {
        $filter_ids = $this->sanitize_ids($filter_ids);
        $strategy = $strategy ? sanitize_key($strategy) : '';
        if ($strategy === 'custom_sidebar' && BeRocket_AAPF_One_Click_Capabilities::supports('mobile_custom_sidebar', $capability)) {
            return $this->get_custom_sidebar_plan($filter_ids);
        }
        if (BeRocket_AAPF_One_Click_Capabilities::supports('filters_above_products', $capability)) {
            return $this->get_above_products_plan($filter_ids, $capability);
        }
        if (BeRocket_AAPF_One_Click_Capabilities::supports('mobile_custom_sidebar', $capability)) {
            return $this->get_custom_sidebar_plan($filter_ids);
        }
        if ($this->can_use_theme_sidebar($desktop_plan)) {
            return $this->get_theme_sidebar_plan($filter_ids, $desktop_plan);
        }
        return array(
            'type' => 'berocket_controlled_sidebar',
            'label' => __('Mobile filters sidebar', 'BeRocket_AJAX_domain'),
            'sidebar_id' => self::SIDEBAR_ID,
            'filter_ids' => $filter_ids,
            'group_settings' => $this->get_mobile_group_settings($filter_ids),
            'available' => false,
            'reason' => array('plugin_controlled_mobile_sidebar'),
            'option_mutations' => array(),
        );
    }

    /**
     * Create the mobile group definition. Placement is deliberately separate
     * so the orchestrator can record all mutations before attaching widgets.
     */
    public function create_group($filter_ids, $setup_id, $capability = null, $strategy = '', $desktop_plan = array()) {
        $plan = $this->resolve($filter_ids, $capability, $strategy, $desktop_plan);
        if (empty($plan['available'])) {
            return new WP_Error('brapf_one_click_mobile_placement_unavailable', __('Mobile placement is not available on this plan.', 'BeRocket_AJAX_domain'));
        }
        if (empty($plan['filter_ids'])) {
            return new WP_Error('brapf_one_click_mobile_group_empty', __('A mobile group needs at least one filter.', 'BeRocket_AJAX_domain'));
        }

        $group_id = wp_insert_post(array(
            'post_title' => __('Featured filters — mobile', 'BeRocket_AJAX_domain'),
            'post_type' => 'br_filters_group',
            'post_status' => 'publish',
        ));
        if (is_wp_error($group_id)) {
            return $group_id;
        }

        $group = BeRocket_AAPF_group_filters::getInstance();
        $settings = $group->get_option($group_id);
        $settings = array_merge($settings, $plan['group_settings']);
        $settings['filters'] = $plan['filter_ids'];
        update_post_meta($group_id, 'br_filters_group', $settings);
        BeRocket_AAPF_One_Click_Setup::mark_post($group_id, $setup_id, 'group');

        $plan['group_id'] = absint($group_id);
        return $plan;
    }

    /**
     * Attach an already-created group to a plugin-owned placement. The return
     * value contains the exact state required for the setup rollback snapshot.
     */
    public function attach_group($group_id, $plan) {
        $group_id = absint($group_id);
        if (!$group_id || empty($plan['available'])) {
            return new WP_Error('brapf_one_click_mobile_attach_failed', __('The mobile filter group could not be placed.', 'BeRocket_AJAX_domain'));
        }
        if (!empty($plan['type']) && $plan['type'] === 'above_products') {
            return $this->attach_above_products($group_id, $plan);
        }
        if (!empty($plan['type']) && $plan['type'] === 'berocket_controlled_sidebar') {
            return $this->attach_custom_sidebar($group_id, $plan);
        }
        if (!empty($plan['type']) && $plan['type'] === 'sidebar_widget') {
            return $this->attach_sidebar_widget($group_id, $plan['sidebar_id'], 'sidebar_widget');
        }
        return new WP_Error('brapf_one_click_mobile_unknown_placement', __('Unknown mobile placement.', 'BeRocket_AJAX_domain'));
    }

    protected function get_above_products_plan($filter_ids, $capability) {
        $preset = BeRocket_AAPF_One_Click_Capabilities::get_group_preset('mobile', $capability);
        return array(
            'type' => 'above_products',
            'label' => __('Above products (mobile)', 'BeRocket_AJAX_domain'),
            'sidebar_id' => '',
            'filter_ids' => $filter_ids,
            'group_settings' => $this->get_mobile_group_settings($filter_ids, $preset),
            'available' => true,
            'reason' => array('plugin_controlled_above_products'),
            'option_mutations' => isset($preset['option_mutations']) ? $preset['option_mutations'] : array(),
        );
    }

    protected function get_custom_sidebar_plan($filter_ids) {
        return array(
            'type' => 'berocket_controlled_sidebar',
            'label' => __('Mobile filters sidebar', 'BeRocket_AJAX_domain'),
            'sidebar_id' => self::SIDEBAR_ID,
            'filter_ids' => $filter_ids,
            'group_settings' => $this->get_mobile_group_settings($filter_ids),
            'available' => true,
            'reason' => array('plugin_controlled_mobile_sidebar'),
            'option_mutations' => array(),
        );
    }

    /** Free uses the same reliable theme sidebar for its mobile-only group. */
    protected function get_theme_sidebar_plan($filter_ids, $desktop_plan) {
        return array(
            'type' => 'sidebar_widget',
            'label' => sprintf(
                __('%s (mobile)', 'BeRocket_AJAX_domain'),
                isset($desktop_plan['label']) ? $desktop_plan['label'] : __('Shop sidebar', 'BeRocket_AJAX_domain')
            ),
            'sidebar_id' => sanitize_key($desktop_plan['sidebar_id']),
            'filter_ids' => $filter_ids,
            'group_settings' => $this->get_mobile_group_settings($filter_ids),
            'available' => true,
            'reason' => array('reliable_theme_sidebar_mobile'),
            'option_mutations' => array(),
        );
    }

    protected function can_use_theme_sidebar($desktop_plan) {
        return is_array($desktop_plan)
            && !empty($desktop_plan['available'])
            && isset($desktop_plan['type']) && $desktop_plan['type'] === 'sidebar_widget'
            && !empty($desktop_plan['sidebar_id']);
    }

    protected function get_mobile_group_settings($filter_ids, $preset = array()) {
        $settings = array(
            'filters' => $filter_ids,
            'hide_group' => array('desktop' => '1'),
            'display_inline' => '0',
            'hidden_clickable' => '0',
            'title_only_theme' => '',
        );
        if (!empty($preset['group_settings']) && is_array($preset['group_settings'])) {
            $settings = array_merge($settings, $preset['group_settings']);
            $settings['hide_group'] = array_merge(
                $settings['hide_group'],
                isset($preset['group_settings']['hide_group']) && is_array($preset['group_settings']['hide_group'])
                    ? $preset['group_settings']['hide_group']
                    : array()
            );
        }
        return $settings;
    }

    protected function attach_above_products($group_id, $plan) {
        $options = get_option('br_filters_options', array());
        $previous = is_array($options) ? $options : array();
        $options = $previous;
        $group_ids = isset($options['elements_above_products']) && is_array($options['elements_above_products'])
            ? array_values(array_unique(array_filter(array_map('absint', $options['elements_above_products']))))
            : array();
        if (!in_array($group_id, $group_ids, true)) {
            $group_ids[] = $group_id;
            $options['elements_above_products'] = $group_ids;
            if (!update_option('br_filters_options', $options)) {
                return new WP_Error('brapf_one_click_mobile_option_save_failed', __('The mobile placement could not be saved.', 'BeRocket_AJAX_domain'));
            }
            wp_cache_delete('br_filters_options', 'berocket_framework_option');
        }
        return array(
            'type' => 'above_products',
            'sidebar_id' => '',
            'widget' => array('id_base' => '', 'number' => 0),
            'previous_plugin_options' => $previous,
            'option_mutations' => $plan['option_mutations'],
        );
    }

    protected function attach_custom_sidebar($group_id, $plan) {
        return $this->attach_sidebar_widget($group_id, self::SIDEBAR_ID, 'berocket_controlled_sidebar');
    }

    protected function attach_sidebar_widget($group_id, $sidebar_id, $type) {
        $sidebar_id = sanitize_key($sidebar_id);
        if (!$sidebar_id) {
            return new WP_Error('brapf_one_click_mobile_sidebar_unavailable', __('A mobile sidebar location is not available.', 'BeRocket_AJAX_domain'));
        }
        $sidebars = get_option('sidebars_widgets', array());
        $instances = get_option('widget_' . self::WIDGET_ID_BASE, array());
        $previous_sidebars = is_array($sidebars) ? $sidebars : array();
        $previous_instances = is_array($instances) ? $instances : array();
        $sidebars = $previous_sidebars;
        $instances = $previous_instances;

        $existing_number = 0;
        foreach ($instances as $number => $instance) {
            if (is_array($instance) && isset($instance['group_id']) && absint($instance['group_id']) === $group_id) {
                $existing_number = absint($number);
                break;
            }
        }

        $numbers = array_filter(array_map('absint', array_keys($instances)));
        $number = $existing_number ? $existing_number : ($numbers ? max($numbers) + 1 : 2);
        $widget_id = self::WIDGET_ID_BASE . '-' . $number;
        if (!isset($sidebars[$sidebar_id]) || !is_array($sidebars[$sidebar_id])) {
            $sidebars[$sidebar_id] = array();
        }
        $created = !in_array($widget_id, $sidebars[$sidebar_id], true);
        if ($created) {
            $sidebars[$sidebar_id][] = $widget_id;
        }
        if (!$existing_number) {
            $instances[$number] = array('group_id' => $group_id);
            $created = true;
        }
        $sidebars_saved = $sidebars === $previous_sidebars || update_option('sidebars_widgets', $sidebars);
        $instances_saved = $instances === $previous_instances || update_option('widget_' . self::WIDGET_ID_BASE, $instances);
        if (!$sidebars_saved || !$instances_saved) {
            return new WP_Error('brapf_one_click_mobile_widget_save_failed', __('The mobile sidebar widget could not be saved.', 'BeRocket_AJAX_domain'));
        }

        return array(
            'type' => sanitize_key($type),
            'sidebar_id' => $sidebar_id,
            'widget' => array('id_base' => self::WIDGET_ID_BASE, 'number' => $number),
            'created' => $created,
            'previous_sidebars_widgets' => $previous_sidebars,
            'previous_widget_instances' => $previous_instances,
        );
    }

    protected function sanitize_ids($ids) {
        if (!is_array($ids)) {
            return array();
        }
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }
}
