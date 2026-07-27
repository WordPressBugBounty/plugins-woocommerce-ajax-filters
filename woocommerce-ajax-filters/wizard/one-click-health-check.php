<?php
/** Verifies that created filters, groups and placements are actually usable. */
class BeRocket_AAPF_One_Click_Health_Check {
    public function check($state = null) {
        $state = $state === null ? BeRocket_AAPF_One_Click_Setup::get_state() : $state;
        $checks = array();
        $filter_ids = isset($state['filters']['ids']) ? $state['filters']['ids'] : array();
        $checks['filters'] = $this->check_posts($filter_ids, 'br_product_filter');
        foreach (array('desktop', 'mobile') as $location) {
            $group = isset($state['groups'][$location]) ? $state['groups'][$location] : array();
            $group_id = isset($group['id']) ? absint($group['id']) : 0;
            $settings = $group_id ? get_post_meta($group_id, 'br_filters_group', true) : array();
            $checks[$location . '_group'] = array(
                'passed' => $group_id && get_post_status($group_id) === 'publish' && is_array($settings)
                    && isset($settings['filters']) && array_values(array_map('absint', $settings['filters'])) === array_values(array_map('absint', $filter_ids)),
                'group_id' => $group_id,
            );
        }
        foreach (array('desktop', 'mobile') as $location) {
            $placement = $this->check_placement($state, $location);
            $checks[$location . '_placement'] = array(
                'passed' => $placement['attached'],
                'type' => $placement['type'],
            );
        }
        $passed = true;
        foreach ($checks as $check) {
            if (empty($check['passed'])) {
                $passed = false;
                break;
            }
        }
        return array(
            'status' => $passed ? 'passed' : 'failed',
            'checked_at' => current_time('mysql', true),
            'checks' => $checks,
        );
    }

    protected function check_posts($post_ids, $type) {
        foreach ((array)$post_ids as $post_id) {
            if (get_post_status($post_id) !== 'publish' || get_post_type($post_id) !== $type) {
                return array('passed' => false);
            }
        }
        return array('passed' => !empty($post_ids), 'count' => count($post_ids));
    }

    /** Verify the stored placement against the live plugin/widget options. */
    protected function check_placement($state, $location) {
        $placement = isset($state['placements'][$location]) && is_array($state['placements'][$location])
            ? $state['placements'][$location]
            : array();
        $group_id = isset($state['groups'][$location]['id']) ? absint($state['groups'][$location]['id']) : 0;
        $type = isset($placement['type']) ? sanitize_key($placement['type']) : '';
        if (!$group_id) {
            return array('type' => $type, 'attached' => false);
        }
        if ($type === 'above_products') {
            $options = get_option('br_filters_options', array());
            $groups = isset($options['elements_above_products']) && is_array($options['elements_above_products'])
                ? array_map('absint', $options['elements_above_products'])
                : array();
            return array('type' => $type, 'attached' => in_array($group_id, $groups, true));
        }
        $sidebar_id = isset($placement['sidebar_id']) ? sanitize_key($placement['sidebar_id']) : '';
        $widget = isset($placement['widget']) && is_array($placement['widget']) ? $placement['widget'] : array();
        $id_base = isset($widget['id_base']) ? sanitize_key($widget['id_base']) : '';
        $number = isset($widget['number']) ? absint($widget['number']) : 0;
        if (!$sidebar_id || !$id_base || !$number) {
            return array('type' => $type, 'attached' => false);
        }
        $instances = get_option('widget_' . $id_base, array());
        $sidebars = get_option('sidebars_widgets', array());
        $widget_id = $id_base . '-' . $number;
        $instance = isset($instances[$number]) ? $instances[$number] : array();
        $attached = isset($sidebars[$sidebar_id]) && is_array($sidebars[$sidebar_id])
            && in_array($widget_id, $sidebars[$sidebar_id], true)
            && is_array($instance) && isset($instance['group_id'])
            && absint($instance['group_id']) === $group_id;
        return array('type' => $type, 'attached' => $attached);
    }
}
