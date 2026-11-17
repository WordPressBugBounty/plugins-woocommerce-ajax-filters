<?php
/** Opt-in filter designs shared by the Free, Pro and Business editions. */
class BeRocket_AAPF_Design_Presets {
    public function __construct() {
        add_filter('brfr_sanitize_option_ajax_filters', array($this, 'sanitize_options'));
        add_filter('BeRocket_AAPF_template_full_content', array($this, 'apply_to_template'), 1400);
        add_filter('BeRocket_AAPF_template_full_element_content', array($this, 'apply_to_template'), 1400);
        add_action('braapf_wp_enqueue_style_after', array($this, 'enqueue_styles'));
    }

    public static function normalize($value) {
        return is_string($value) && in_array($value, array('compact', 'soft', 'high_contrast'), true) ? $value : '';
    }

    public function sanitize_options($options) {
        if (is_array($options)) {
            $options['filter_design_preset'] = self::normalize(isset($options['filter_design_preset']) ? $options['filter_design_preset'] : '');
        }
        return $options;
    }

    private function selected_preset() {
        $options = BeRocket_AAPF::getInstance()->get_option();
        return self::normalize(isset($options['filter_design_preset']) ? $options['filter_design_preset'] : '');
    }

    public function apply_to_template($template) {
        $preset = $this->selected_preset();
        if ($preset === '' || !isset($template['template']['attributes']) || !is_array($template['template']['attributes'])) {
            return $template;
        }
        $classes = isset($template['template']['attributes']['class']) ? $template['template']['attributes']['class'] : array();
        if (is_string($classes)) {
            $classes = array($classes);
        }
        if (!is_array($classes)) {
            return $template;
        }
        // A named entry also keeps repeated template passes idempotent.
        $classes['design_preset'] = 'bapf_design_preset_' . $preset;
        $template['template']['attributes']['class'] = $classes;
        return $template;
    }

    public function enqueue_styles($handle) {
        // Follow the existing filter stylesheet, including its on-demand/footer mode.
        // The file stays separate from the generated bundles in all release builds.
        if ($handle === 'berocket_aapf_widget-style' && $this->selected_preset() !== '') {
            wp_enqueue_style(
                'berocket-aapf-design-presets',
                plugins_url('assets/frontend/css/design-presets.css', BeRocket_AJAX_filters_file),
                array('berocket_aapf_widget-style'),
                BeRocket_AJAX_filters_version
            );
        }
    }
}
new BeRocket_AAPF_Design_Presets();
