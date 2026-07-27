<?php
/**
 * Selects the most reliable desktop location before any widgets are changed.
 */
class BeRocket_AAPF_One_Click_Desktop_Placement {
    public function resolve($registered_sidebars = null, $sidebars_widgets = null, $capability = null) {
        if ($registered_sidebars === null) {
            $registered_sidebars = isset($GLOBALS['wp_registered_sidebars']) && is_array($GLOBALS['wp_registered_sidebars'])
                ? $GLOBALS['wp_registered_sidebars']
                : array();
        }
        if ($sidebars_widgets === null) {
            $sidebars_widgets = get_option('sidebars_widgets', array());
        }
        $archive_sidebars = apply_filters('brapf_one_click_woo_archive_sidebars', array());
        $candidates = array();
        foreach ((array)$registered_sidebars as $sidebar_id => $sidebar) {
            $sidebar_id = isset($sidebar['id']) ? $sidebar['id'] : $sidebar_id;
            $widgets = isset($sidebars_widgets[$sidebar_id]) && is_array($sidebars_widgets[$sidebar_id])
                ? array_values(array_filter($sidebars_widgets[$sidebar_id], 'is_string'))
                : array();
            $candidate = $this->score_sidebar($sidebar_id, $sidebar, $widgets, $archive_sidebars);
            if ($candidate['score'] > 0) {
                $candidates[] = $candidate;
            }
        }
        usort($candidates, array($this, 'sort_candidates'));
        if (!empty($candidates)) {
            $best = $candidates[0];
            if ($best['reliable']) {
                return $this->get_sidebar_plan($best, $candidates);
            }
        }
        $active_theme_candidates = array_values(array_filter($candidates, function($candidate) {
            return $candidate['widget_count'] > 0 && !$candidate['is_non_catalog'];
        }));
        if (count($active_theme_candidates) === 1) {
            $only_active_sidebar = $active_theme_candidates[0];
            $only_active_sidebar['confidence'] = 'medium';
            $only_active_sidebar['reason'][] = 'only_active_theme_sidebar';
            return $this->get_sidebar_plan($only_active_sidebar, $candidates);
        }
        return $this->get_fallback($capability, $candidates);
    }

    protected function get_sidebar_plan($sidebar, $candidates) {
        return array(
            'type' => 'sidebar_widget',
            'sidebar_id' => $sidebar['id'],
            'label' => $sidebar['name'],
            'confidence' => $sidebar['confidence'],
            'reason' => $sidebar['reason'],
            'is_fallback' => false,
            'available' => true,
            'candidates' => $candidates,
        );
    }

    protected function score_sidebar($sidebar_id, $sidebar, $widgets, $archive_sidebars) {
        $name = isset($sidebar['name']) ? $sidebar['name'] : $sidebar_id;
        $description = isset($sidebar['description']) ? $sidebar['description'] : '';
        $text = strtolower($sidebar_id . ' ' . $name . ' ' . $description);
        $is_active = !empty($widgets);
        $is_archive_sidebar = in_array($sidebar_id, (array)$archive_sidebars, true);
        $has_filter_widget = false;
        foreach ($widgets as $widget) {
            if (strpos($widget, 'berocket_aapf_group') === 0 || strpos($widget, 'berocket_aapf_single') === 0) {
                $has_filter_widget = true;
                break;
            }
        }
        $is_shop_sidebar = $this->text_has_any($text, array('shop', 'product', 'woocommerce', 'woo', 'catalog', 'store'));
        $is_non_catalog = $this->text_has_any($text, array('footer', 'header', 'menu', 'social', 'blog', 'post', 'page'));
        $score = 0;
        $reasons = array();
        if ($is_archive_sidebar) {
            $score += 200;
            $reasons[] = 'theme_reports_woo_archive_sidebar';
        }
        if ($is_shop_sidebar) {
            $score += 120;
            $reasons[] = 'shop_or_product_sidebar';
        }
        if ($is_active) {
            $score += 30;
            $reasons[] = 'active_theme_sidebar';
        }
        if ($has_filter_widget) {
            $score += 70;
            $reasons[] = 'existing_filters_widget';
        }
        if ($is_non_catalog) {
            $score -= 180;
            $reasons[] = 'non_catalog_sidebar';
        }
        $reliable = !$is_non_catalog && ($is_archive_sidebar || ($is_shop_sidebar && $is_active) || $has_filter_widget);
        return array(
            'id' => $sidebar_id,
            'name' => $name,
            'score' => $score,
            'confidence' => $is_archive_sidebar || ($is_shop_sidebar && $is_active) || $has_filter_widget ? 'high' : 'low',
            'reliable' => $reliable,
            'is_non_catalog' => $is_non_catalog,
            'reason' => $reasons,
            'widget_count' => count($widgets),
        );
    }

    protected function get_fallback($capability, $candidates) {
        if (BeRocket_AAPF_One_Click_Capabilities::supports('filters_above_products', $capability)) {
            return array(
                'type' => 'above_products',
                'sidebar_id' => '',
                'label' => __('Filters above products', 'BeRocket_AJAX_domain'),
                'confidence' => 'controlled',
                'reason' => array('no_reliable_theme_sidebar', 'berocket_above_products_fallback'),
                'is_fallback' => true,
                'available' => true,
                'option_mutation' => array(
                    'option' => 'br_filters_options',
                    'path' => array('elements_above_products'),
                    'operation' => 'append_group_id',
                ),
                'candidates' => $candidates,
            );
        }
        return array(
            'type' => 'berocket_controlled_sidebar',
            'sidebar_id' => 'berocket-ajax-filters',
            'label' => __('BeRocket Filters sidebar', 'BeRocket_AJAX_domain'),
            'confidence' => 'controlled',
            'reason' => array('no_reliable_theme_sidebar', 'berocket_controlled_sidebar_fallback'),
            'is_fallback' => true,
            'available' => BeRocket_AAPF_One_Click_Capabilities::supports('mobile_custom_sidebar', $capability),
            'required_feature' => 'mobile_custom_sidebar',
            'candidates' => $candidates,
        );
    }

    protected function text_has_any($text, $needles) {
        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function sort_candidates($first, $second) {
        if ($first['score'] === $second['score']) {
            return strcmp($first['id'], $second['id']);
        }
        return $first['score'] > $second['score'] ? -1 : 1;
    }
}
