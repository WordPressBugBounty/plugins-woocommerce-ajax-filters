<?php
/** Default visual treatment for useful flat checkbox filters. */
class BeRocket_AAPF_Checkbox_Style_Defaults {
    public function __construct() {
        add_filter('brapf_one_click_filter_definition_settings', array($this, 'apply_pink_labels_preset'), 20, 3);
    }

    /**
     * Size, Fit, Tags and other flat checkbox filters work best as compact
     * selectable labels. Hierarchical taxonomies keep their tree treatment.
     */
    public function apply_pink_labels_preset($settings, $recommendation, $identity) {
        if (!$this->is_flat_checkbox_filter($settings)) {
            return $settings;
        }
        $settings['style'] = 'pink_labels_checkbox';
        return $settings;
    }

    protected function is_flat_checkbox_filter($settings) {
        if (!is_array($settings) || (isset($settings['style']) && $settings['style'] !== 'grey-check')) {
            return false;
        }
        $type = isset($settings['filter_type']) ? sanitize_key($settings['filter_type']) : '';
        if (!in_array($type, array('attribute', 'tag', 'custom_taxonomy', '_stock_status', '_sale'), true)) {
            return false;
        }
        if ($type === 'custom_taxonomy') {
            $taxonomy = isset($settings['custom_taxonomy']) ? sanitize_key($settings['custom_taxonomy']) : '';
            if ($taxonomy === 'product_cat' || !empty($settings['hide_child_attributes'])) {
                return false;
            }
            if ($taxonomy && function_exists('is_taxonomy_hierarchical') && is_taxonomy_hierarchical($taxonomy)) {
                return false;
            }
        }
        return true;
    }
}
new BeRocket_AAPF_Checkbox_Style_Defaults();
