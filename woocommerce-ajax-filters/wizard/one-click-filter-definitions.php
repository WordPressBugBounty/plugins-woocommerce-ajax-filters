<?php
/**
 * Owns one-click filter definitions. Groups receive the returned IDs, never
 * their own copied filter configuration.
 */
class BeRocket_AAPF_One_Click_Filter_Definitions {
    const POST_TYPE = 'br_product_filter';
    const SETTINGS_META = 'br_product_filter';

    /**
     * Resolve each recommendation to one existing definition or create a new
     * one. $existing_filters is injectable for tests; production callers pass
     * null and the service reads published filter definitions once.
     */
    public function create_or_reuse($recommendations, $setup_id, $existing_filters = null) {
        $result = array(
            'ids' => array(),
            'created_ids' => array(),
            'reused_ids' => array(),
            'definitions' => array(),
        );
        $existing = $this->index_existing_filters($existing_filters === null ? $this->get_existing_filters() : $existing_filters);
        foreach ((array)$recommendations as $recommendation) {
            $definition = $this->normalize_recommendation($recommendation);
            if (empty($definition)) {
                continue;
            }
            $identity = $definition['identity'];
            if (isset($result['definitions'][$identity])) {
                continue;
            }
            if (!empty($existing[$identity])) {
                $filter_id = array_shift($existing[$identity]);
                $created = false;
            } else {
                $filter_id = $this->create_filter($definition, $setup_id);
                if (is_wp_error($filter_id)) {
                    $this->delete_created_filters($result['created_ids'], $setup_id);
                    return $filter_id;
                }
                $created = true;
                $existing[$identity][] = $filter_id;
            }
            $filter_id = absint($filter_id);
            $result['ids'][] = $filter_id;
            $result['definitions'][$identity] = array(
                'id' => $filter_id,
                'recommendation_key' => isset($recommendation['key']) ? sanitize_key($recommendation['key']) : '',
                'created' => $created,
            );
            // Extensions may migrate their own generated definition after a
            // stable-identity reuse. They must never alter its identity.
            do_action('brapf_one_click_filter_definition_resolved', $filter_id, $definition, $setup_id, $created);
            if ($created) {
                $result['created_ids'][] = $filter_id;
            } else {
                $result['reused_ids'][] = $filter_id;
            }
        }
        $result['ids'] = $this->sanitize_ids($result['ids']);
        $result['created_ids'] = $this->sanitize_ids($result['created_ids']);
        $result['reused_ids'] = $this->sanitize_ids($result['reused_ids']);
        return $result;
    }

    /** A stable identity that ignores presentation settings and post titles. */
    public function get_definition_identity($settings) {
        $settings = is_array($settings) ? $settings : array();
        $type = isset($settings['filter_type']) ? sanitize_key($settings['filter_type']) : '';
        $attribute = isset($settings['attribute']) ? sanitize_key($settings['attribute']) : '';
        $taxonomy = isset($settings['custom_taxonomy']) ? sanitize_key($settings['custom_taxonomy']) : '';
        if ($type === 'price' || ($type === 'attribute' && $attribute === 'price')) {
            return 'price';
        }
        if ($type === 'all_product_cat' || $type === 'product_cat'
            || ($type === 'custom_taxonomy' && $taxonomy === 'product_cat')) {
            return 'taxonomy:product_cat';
        }
        if ($type === 'berocket_brand') {
            return 'brand:berocket_brand';
        }
        if ($type === 'attribute' && $attribute) {
            return 'attribute:' . $attribute;
        }
        if ($type === 'custom_taxonomy' && $taxonomy) {
            return 'taxonomy:' . $taxonomy;
        }
        if ($type === 'tag') {
            return 'taxonomy:product_tag';
        }
        if ($type === 'grouped_taxonomy' && !empty($settings['_brapf_one_click_featured_values'])) {
            return 'grouped:one_click_featured_values';
        }
        if ($type) {
            return 'type:' . $type;
        }
        return '';
    }

    /** Turn ranker output into the exact settings shape stored on the post. */
    public function normalize_recommendation($recommendation) {
        if (!is_array($recommendation) || empty($recommendation['filter_definition'])
            || !is_array($recommendation['filter_definition'])) {
            return array();
        }
        $settings = $recommendation['filter_definition'];
        $settings['widget_type'] = !empty($settings['widget_type']) ? $settings['widget_type'] : 'filter';
        $settings['filter_title'] = $this->get_filter_title($recommendation);
        $type = isset($settings['filter_type']) ? sanitize_key($settings['filter_type']) : '';
        if ($type === 'all_product_cat') {
            $settings['filter_type'] = 'custom_taxonomy';
            $settings['custom_taxonomy'] = 'product_cat';
            unset($settings['attribute']);
        }
        $identity = $this->get_definition_identity($settings);
        if (!$identity) {
            return array();
        }
        $settings = apply_filters('brapf_one_click_filter_definition_settings', $settings, $recommendation, $identity);
        return array(
            'identity' => $identity,
            'title' => $this->get_filter_title($recommendation),
            'post_title' => !empty($recommendation['post_title'])
                ? sanitize_text_field($recommendation['post_title'])
                : $this->get_filter_title($recommendation),
            'post_name' => !empty($recommendation['post_name'])
                ? sanitize_title($recommendation['post_name'])
                : '',
            'settings' => $settings,
            'details' => isset($recommendation['details']) && is_array($recommendation['details']) ? $recommendation['details'] : array(),
        );
    }

    protected function get_existing_filters() {
        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ));
        $filters = array();
        foreach ((array)$query->posts as $filter_id) {
            $filters[] = array(
                'id' => absint($filter_id),
                'settings' => get_post_meta($filter_id, self::SETTINGS_META, true),
            );
        }
        return $filters;
    }

    protected function index_existing_filters($filters) {
        $index = array();
        foreach ((array)$filters as $key => $filter) {
            if (is_array($filter) && isset($filter['id'])) {
                $filter_id = absint($filter['id']);
                $settings = isset($filter['settings']) ? $filter['settings'] : array();
            } else {
                $filter_id = absint($key);
                $settings = $filter;
            }
            $identity = $this->get_definition_identity($settings);
            if (!$filter_id || !$identity) {
                continue;
            }
            if (!isset($index[$identity])) {
                $index[$identity] = array();
            }
            $index[$identity][] = $filter_id;
        }
        return $index;
    }

    protected function create_filter($definition, $setup_id) {
        $post_data = array(
            'post_title' => sprintf(__('%s — 1-click', 'BeRocket_AJAX_domain'), $definition['post_title']),
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
        );
        if (!empty($definition['post_name'])) {
            $post_data['post_name'] = $definition['post_name'];
        }
        $filter_id = wp_insert_post($post_data);
        if (is_wp_error($filter_id)) {
            return $filter_id;
        }
        $filter = BeRocket_AAPF_single_filter::getInstance();
        $settings = array_merge($filter->get_option($filter_id), $definition['settings']);
        update_post_meta($filter_id, self::SETTINGS_META, $settings);
        BeRocket_AAPF_One_Click_Setup::mark_post($filter_id, $setup_id, 'filter');
        // Paid modules may add diagnostic metadata. This action must never be
        // used to change the stable definition identity or saved settings.
        do_action('brapf_one_click_filter_definition_created', absint($filter_id), $definition, $setup_id);
        return absint($filter_id);
    }

    /**
     * Upgrade only the exact old generated title. A renamed title is a manual
     * edit and must stay untouched.
     */
    public static function migrate_legacy_post_titles() {
        if (!is_admin() || !current_user_can('manage_woocommerce')) {
            return;
        }
        $state = BeRocket_AAPF_One_Click_Setup::get_state();
        if (empty($state['setup_id'])) {
            return;
        }
        foreach ((array)$state['filters']['ids'] as $filter_id) {
            $filter_id = absint($filter_id);
            if (!$filter_id || !BeRocket_AAPF_One_Click_Setup::is_setup_post($filter_id, $state['setup_id'])) {
                continue;
            }
            $settings = get_post_meta($filter_id, self::SETTINGS_META, true);
            $filter_title = is_array($settings) && !empty($settings['filter_title'])
                ? sanitize_text_field($settings['filter_title'])
                : '';
            if (!$filter_title) {
                continue;
            }
            $legacy_title = sprintf(__('Featured filter — %s', 'BeRocket_AJAX_domain'), $filter_title);
            $current_title = get_post_field('post_title', $filter_id);
            $is_legacy_ai_featured = $current_title === __('Featured filter', 'BeRocket_AJAX_domain')
                && !empty(get_post_meta($filter_id, '_brapf_one_click_ai_featured_origin', true));
            if ($current_title !== $legacy_title && !$is_legacy_ai_featured) {
                continue;
            }
            wp_update_post(array(
                'ID' => $filter_id,
                'post_title' => $is_legacy_ai_featured
                    ? sprintf(__('%s — 1-click', 'BeRocket_AJAX_domain'), __('Featured filter', 'BeRocket_AJAX_domain'))
                    : sprintf(__('%s — 1-click', 'BeRocket_AJAX_domain'), $filter_title),
            ));
        }
    }

    protected function get_filter_title($recommendation) {
        $key = isset($recommendation['key']) ? $recommendation['key'] : '';
        $titles = array(
            'price' => __('Price', 'BeRocket_AJAX_domain'),
            'categories' => __('Categories', 'BeRocket_AJAX_domain'),
            'availability' => __('Availability', 'BeRocket_AJAX_domain'),
            'on_sale' => __('On sale', 'BeRocket_AJAX_domain'),
            'rating' => __('Rating', 'BeRocket_AJAX_domain'),
            'tags' => __('Tags', 'BeRocket_AJAX_domain'),
            'featured_values' => __('Featured products', 'BeRocket_AJAX_domain'),
        );
        if (strpos($key, 'brand:') === 0) {
            return __('Brands', 'BeRocket_AJAX_domain');
        }
        if (strpos($key, 'attribute:') === 0 && !empty($recommendation['details']['attribute_metrics']['label'])) {
            return sanitize_text_field($recommendation['details']['attribute_metrics']['label']);
        }
        return isset($titles[$key]) ? $titles[$key] : __('Product filter', 'BeRocket_AJAX_domain');
    }

    /** A failed definition batch must not leave posts the orchestrator cannot yet record. */
    protected function delete_created_filters($filter_ids, $setup_id) {
        foreach ($this->sanitize_ids($filter_ids) as $filter_id) {
            if (BeRocket_AAPF_One_Click_Setup::is_setup_post($filter_id, $setup_id)) {
                wp_delete_post($filter_id, true);
            }
        }
    }

    protected function sanitize_ids($ids) {
        return array_values(array_unique(array_filter(array_map('absint', (array)$ids))));
    }
}

add_action('admin_init', array('BeRocket_AAPF_One_Click_Filter_Definitions', 'migrate_legacy_post_titles'));
