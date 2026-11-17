<?php
/** Selected-value badges for multi-select filter titles in every edition. */
class BeRocket_AAPF_Selected_Values_Count {
    public function __construct() {
        add_filter('brfr_sanitize_option_ajax_filters', array($this, 'sanitize_options'));
        add_filter('BeRocket_AAPF_template_full_content', array($this, 'add_badge'), 1600, 3);
        add_action('braapf_wp_enqueue_style_after', array($this, 'enqueue_styles'));
        add_action('braapf_wp_enqueue_script_after', array($this, 'enqueue_scripts'));
    }

    public static function normalize($value) {
        return in_array($value, array('1', 1, true), true) ? '1' : '';
    }

    public function sanitize_options($options) {
        if (is_array($options)) {
            $options['show_selected_values_count'] = self::normalize(isset($options['show_selected_values_count']) ? $options['show_selected_values_count'] : '');
        }
        return $options;
    }

    private function enabled() {
        $options = BeRocket_AAPF::getInstance()->get_option();
        return isset($options['show_selected_values_count']) && self::normalize($options['show_selected_values_count']) === '1';
    }

    private function selected_values($node, $kind, &$values) {
        if (!is_array($node)) { return; }
        $attributes = isset($node['attributes']) && is_array($node['attributes']) ? $node['attributes'] : array();
        $selected = $kind === 'checkbox'
            ? (isset($node['tag'], $attributes['type'], $attributes['checked']) && $node['tag'] === 'input' && $attributes['type'] === 'checkbox')
            : (isset($node['tag'], $attributes['selected']) && $node['tag'] === 'option');
        if ($selected && isset($attributes['value']) && is_scalar($attributes['value']) && (string)$attributes['value'] !== '') {
            $values[(string)$attributes['value']] = true;
        }
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) { $this->selected_values($child, $kind, $values); }
        }
    }

    public function add_badge($template, $terms, $settings) {
        $kind = isset($settings['new_template']) ? $settings['new_template'] : '';
        if (!$this->enabled() || !in_array($kind, array('checkbox', 'select'), true)
            || !empty($settings['single_selection'])
            || (isset($settings['attribute']) && $settings['attribute'] === 'price')
            || (isset($template['template']['attributes']['data-taxonomy']) && $template['template']['attributes']['data-taxonomy'] === 'price')
            || empty($template['template']['content']['header']['content']['title']['content']['title'])) {
            return $template;
        }
        $values = array();
        if (isset($template['template']['content']['filter'])) {
            $this->selected_values($template['template']['content']['filter'], $kind, $values);
        }
        $count = count($values);
        $label = __('Selected values: %s', 'BeRocket_AJAX_domain');
        $attributes = array('class' => 'bapf_selected_count', 'data-count-kind' => $kind, 'data-count-label' => $label);
        if (!$count) { $attributes['hidden'] = 'hidden'; }
        $title = $template['template']['content']['header']['content']['title'];
        $title['attributes']['class'] = isset($title['attributes']['class']) ? (array)$title['attributes']['class'] : array();
        $title['attributes']['class']['selected_values_count'] = 'bapf_has_selected_count';
        $template['template']['content']['header']['content']['title'] = $title;
        $template['template']['content']['header']['content']['title']['content']['selected_values_count'] = array(
            'type' => 'tag', 'tag' => 'span', 'attributes' => $attributes,
            'content' => array(
                'number' => array('type' => 'tag', 'tag' => 'span', 'attributes' => array('class' => 'bapf_selected_count_number', 'aria-hidden' => 'true'), 'content' => array((string)$count)),
                'label' => array('type' => 'tag', 'tag' => 'span', 'attributes' => array('class' => 'bapf_selected_count_label'), 'content' => array(esc_html(sprintf($label, $count)))),
            ),
        );
        return $template;
    }

    public function enqueue_styles($handle) {
        if ($handle === 'berocket_aapf_widget-style' && $this->enabled()) {
            wp_enqueue_style('berocket-aapf-selected-values-count', plugins_url('assets/frontend/css/selected-values-count.css', BeRocket_AJAX_filters_file), array($handle), BeRocket_AJAX_filters_version);
        }
    }

    public function enqueue_scripts($handle) {
        if ($handle === 'berocket_aapf_widget-script' && $this->enabled()) {
            wp_enqueue_script('berocket-aapf-selected-values-count', plugins_url('assets/frontend/js/selected-values-count.js', BeRocket_AJAX_filters_file), array('jquery', $handle), BeRocket_AJAX_filters_version, true);
        }
    }
}
new BeRocket_AAPF_Selected_Values_Count();
