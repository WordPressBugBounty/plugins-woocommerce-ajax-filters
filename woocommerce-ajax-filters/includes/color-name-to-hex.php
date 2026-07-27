<?php
/**
 * Resolves familiar colour names for visual Color filters.
 *
 * Values are intentionally local and deterministic: catalog terms are never
 * sent to a third-party service. The map covers CSS names plus common retail
 * aliases and can be extended through brapf_color_name_to_hex_map.
 */
class BeRocket_AAPF_Color_Name_To_Hex {
    const TERM_META_KEY = 'color';

    public function __construct() {
        add_filter('brapf_one_click_filter_definition_settings', array($this, 'apply_one_click_color_preset'), 20, 3);
        add_action('brapf_one_click_filter_definition_created', array($this, 'populate_one_click_filter_colors'), 20, 3);
        add_filter('berocket_aapf_color_term_select_metadata', array($this, 'get_inferred_term_color'), 20, 3);
    }

    /** Make an eligible Color attribute a useful visual filter from the start. */
    public function apply_one_click_color_preset($settings, $recommendation, $identity) {
        $semantic_kind = isset($recommendation['details']['attribute_metrics']['semantic']['kind'])
            ? sanitize_key($recommendation['details']['attribute_metrics']['semantic']['kind'])
            : '';
        if ($semantic_kind !== 'color') {
            return $settings;
        }
        $settings['style'] = 'square_with_shadow_color';
        $settings['use_value_with_color'] = 'tooltip';
        return $settings;
    }

    /** Persist inferred colours for a newly generated Color filter. */
    public function populate_one_click_filter_colors($filter_id, $definition, $setup_id) {
        if (!empty($definition['settings']) && is_array($definition['settings'])) {
            $this->populate_filter_term_colors($definition['settings']);
        }
    }

    /**
     * Supply the inferred value to both the storefront and the color editor.
     * The save hook above persists it later; this fallback makes a new Color
     * filter useful immediately while preserving an intentionally blank meta.
     */
    public function get_inferred_term_color($value, $term, $meta_key) {
        if ($meta_key !== self::TERM_META_KEY || !empty($value) || !is_object($term) || empty($term->term_id) || empty($term->name)) {
            return $value;
        }
        // berocket_term_get_metadata calls this hook before reading raw meta.
        // Returning false here lets the user's saved value win, including an
        // intentionally blank value.
        if ($this->has_stored_term_value($term->term_id, self::TERM_META_KEY)) {
            return false;
        }
        $hex = self::name_to_hex($term->name);
        return $hex === null ? $value : array($hex);
    }

    /** Fill only metadata which has never been set by an administrator. */
    public function populate_filter_term_colors($settings) {
        if (!$this->is_color_style($settings)) {
            return;
        }
        $taxonomy = $this->get_filter_taxonomy($settings);
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            return;
        }
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));
        if (is_wp_error($terms) || !is_array($terms)) {
            return;
        }
        foreach ($terms as $term) {
            if (!is_object($term) || empty($term->term_id) || $this->has_stored_term_value($term->term_id, self::TERM_META_KEY)) {
                continue;
            }
            $hex = self::name_to_hex(isset($term->name) ? $term->name : '');
            if ($hex !== null) {
                update_metadata('berocket_term', $term->term_id, self::TERM_META_KEY, $hex);
            }
        }
    }

    /** Convert a CSS/common retail colour name or a valid hex literal to RRGGBB. */
    public static function name_to_hex($value) {
        $value = trim(wp_strip_all_tags((string) $value));
        if (preg_match('/^#?([a-f0-9]{3}|[a-f0-9]{6})$/i', $value, $matches)) {
            $hex = strtolower($matches[1]);
            return strlen($hex) === 3
                ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]
                : $hex;
        }
        $name = self::normalize_name($value);
        $colors = apply_filters('brapf_color_name_to_hex_map', self::get_color_map());
        return is_array($colors) && isset($colors[$name]) ? ltrim(strtolower($colors[$name]), '#') : null;
    }

    protected function is_color_style($settings) {
        return is_array($settings) && isset($settings['style']) && sanitize_key($settings['style']) === 'color';
    }

    protected function get_filter_taxonomy($settings) {
        $filter_type = isset($settings['filter_type']) ? sanitize_key($settings['filter_type']) : '';
        if ($filter_type === 'attribute' && !empty($settings['attribute'])) {
            return sanitize_key($settings['attribute']);
        }
        if (in_array($filter_type, array('custom_taxonomy', 'product_cat', 'tag'), true)) {
            if (!empty($settings['custom_taxonomy'])) {
                return sanitize_key($settings['custom_taxonomy']);
            }
            return $filter_type === 'tag' ? 'product_tag' : ($filter_type === 'product_cat' ? 'product_cat' : '');
        }
        return '';
    }

    /** Raw metadata check: a filter hook must never override an edited value. */
    protected function has_stored_term_value($term_id, $meta_key) {
        $values = get_metadata('berocket_term', absint($term_id), $meta_key, false);
        return is_array($values) && count($values) > 0;
    }

    protected static function normalize_name($name) {
        $name = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $name = preg_replace('/[\-_]+/', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /** CSS names plus practical catalog aliases that are not CSS keywords. */
    protected static function get_color_map() {
        return array(
            'black' => '000000', 'white' => 'ffffff', 'red' => 'ff0000', 'green' => '008000', 'blue' => '0000ff',
            'yellow' => 'ffff00', 'orange' => 'ffa500', 'purple' => '800080', 'pink' => 'ffc0cb', 'brown' => 'a52a2a',
            'gray' => '808080', 'grey' => '808080', 'silver' => 'c0c0c0', 'charcoal' => '36454f', 'charcoal gray' => '36454f',
            'charcoal grey' => '36454f', 'dark gray' => 'a9a9a9', 'dark grey' => 'a9a9a9', 'light gray' => 'd3d3d3', 'light grey' => 'd3d3d3',
            'navy' => '000080', 'navy blue' => '000080', 'royal blue' => '4169e1', 'sky blue' => '87ceeb', 'light blue' => 'add8e6',
            'dark blue' => '00008b', 'teal' => '008080', 'turquoise' => '40e0d0', 'aqua' => '00ffff', 'cyan' => '00ffff',
            'lime' => '00ff00', 'olive' => '808000', 'forest green' => '228b22', 'dark green' => '006400', 'light green' => '90ee90',
            'mint' => '98ff98', 'emerald' => '50c878', 'khaki' => 'f0e68c', 'beige' => 'f5f5dc', 'cream' => 'fffdd0',
            'ivory' => 'fffff0', 'tan' => 'd2b48c', 'sand' => 'c2b280', 'gold' => 'ffd700', 'mustard' => 'ffdb58',
            'coral' => 'ff7f50', 'salmon' => 'fa8072', 'peach' => 'ffe5b4', 'maroon' => '800000', 'burgundy' => '800020',
            'violet' => 'ee82ee', 'indigo' => '4b0082', 'lavender' => 'e6e6fa', 'magenta' => 'ff00ff', 'fuchsia' => 'ff00ff',
            'rose' => 'ff007f', 'hot pink' => 'ff69b4', 'black white' => '000000',
            'чорний' => '000000', 'білий' => 'ffffff', 'червоний' => 'ff0000', 'зелений' => '008000', 'синій' => '0000ff',
            'жовтий' => 'ffff00', 'помаранчевий' => 'ffa500', 'фіолетовий' => '800080', 'рожевий' => 'ffc0cb', 'коричневий' => 'a52a2a',
            'сірий' => '808080', 'блакитний' => '87ceeb', 'чорный' => '000000', 'белый' => 'ffffff', 'красный' => 'ff0000',
            'зеленый' => '008000', 'синий' => '0000ff', 'желтый' => 'ffff00', 'оранжевый' => 'ffa500', 'фиолетовый' => '800080',
            'розовый' => 'ffc0cb', 'коричневый' => 'a52a2a', 'серый' => '808080', 'голубой' => '87ceeb',
        );
    }
}
new BeRocket_AAPF_Color_Name_To_Hex();
