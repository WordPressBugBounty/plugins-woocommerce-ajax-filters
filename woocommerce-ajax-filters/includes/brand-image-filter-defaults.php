<?php
/**
 * Makes Brand filters use existing brand logos without duplicating them.
 *
 * Image values are kept in the filter's existing berocket_term metadata,
 * while brand plugins remain the source of truth for their own term images.
 */
class BeRocket_AAPF_Brand_Image_Filter_Defaults {
    const TERM_META_KEY = 'image';
    protected $coverage_cache = array();

    public function __construct() {
        add_filter('brapf_one_click_filter_definition_settings', array($this, 'apply_one_click_brand_preset'), 20, 3);
        add_action('brapf_one_click_filter_definition_created', array($this, 'populate_one_click_filter_images'), 20, 3);
        add_filter('berocket_aapf_color_term_select_metadata', array($this, 'get_inferred_term_image'), 20, 3);
    }

    /** Use logos only when they are complete enough to make a good filter. */
    public function apply_one_click_brand_preset($settings, $recommendation, $identity) {
        $taxonomy = $this->get_brand_taxonomy($settings, $recommendation, $identity);
        if (!$taxonomy || !$this->has_sufficient_brand_images($taxonomy)) {
            return $settings;
        }
        $settings['style'] = 'image_woborder';
        $settings['color_image_block_size'] = 'h3em w3em';
        $settings['use_value_with_color'] = 'tooltip';
        return $settings;
    }

    /** Persist only logo values which are absent from the filter metadata. */
    public function populate_one_click_filter_images($filter_id, $definition, $setup_id) {
        if (!empty($definition['settings']) && is_array($definition['settings'])) {
            $this->populate_filter_brand_images($definition['settings']);
        }
    }

    /** Supply a logo immediately in storefront/admin image fields before a save persists it. */
    public function get_inferred_term_image($value, $term, $meta_key) {
        if ($meta_key !== self::TERM_META_KEY || !empty($value) || !is_object($term)
            || empty($term->term_id) || empty($term->taxonomy) || !$this->is_brand_taxonomy($term->taxonomy)) {
            return $value;
        }
        if ($this->has_stored_term_value($term->term_id, self::TERM_META_KEY)) {
            return false;
        }
        $image_url = $this->get_brand_image_url($term);
        return $image_url === '' ? $value : array($image_url);
    }

    /** Fill empty Image values when the selected visual style is Image. */
    public function populate_filter_brand_images($settings) {
        if (!$this->is_image_style($settings)) {
            return;
        }
        $taxonomy = $this->get_brand_taxonomy($settings);
        if (!$taxonomy || !taxonomy_exists($taxonomy)) {
            return;
        }
        $terms = $this->get_brand_terms($taxonomy);
        foreach ($terms as $term) {
            if (empty($term->term_id) || $this->has_stored_term_value($term->term_id, self::TERM_META_KEY)) {
                continue;
            }
            $image_url = $this->get_brand_image_url($term);
            if ($image_url !== '') {
                update_metadata('berocket_term', $term->term_id, self::TERM_META_KEY, $image_url);
            }
        }
    }

    /** Resolve a brand logo URL from our Brands, Woo Brands, or known extensions. */
    public function get_brand_image_url($term) {
        if (!is_object($term) || empty($term->term_id) || empty($term->taxonomy)) {
            return '';
        }
        foreach ($this->get_image_meta_keys($term->taxonomy) as $meta_key) {
            $url = $this->normalize_image_value(get_term_meta($term->term_id, $meta_key, true));
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }

    protected function has_sufficient_brand_images($taxonomy) {
        if (isset($this->coverage_cache[$taxonomy])) {
            return $this->coverage_cache[$taxonomy];
        }
        $terms = $this->get_brand_terms($taxonomy);
        $term_count = count($terms);
        if (!$term_count) {
            return $this->coverage_cache[$taxonomy] = false;
        }
        $image_count = 0;
        foreach ($terms as $term) {
            if ($this->get_brand_image_url($term) !== '') {
                $image_count++;
            }
        }
        $minimum_coverage = (float) apply_filters('brapf_brand_image_filter_minimum_coverage', 0.75, $taxonomy);
        $minimum_coverage = max(0, min(1, $minimum_coverage));
        return $this->coverage_cache[$taxonomy] = $image_count > 0 && ($image_count / $term_count) >= $minimum_coverage;
    }

    protected function get_brand_terms($taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
        return is_wp_error($terms) || !is_array($terms) ? array() : $terms;
    }

    protected function get_brand_taxonomy($settings, $recommendation = array(), $identity = '') {
        $taxonomy = '';
        $filter_type = isset($settings['filter_type']) ? sanitize_key($settings['filter_type']) : '';
        if ($filter_type === 'berocket_brand') {
            $taxonomy = 'berocket_brand';
        } elseif ($filter_type === 'custom_taxonomy' && !empty($settings['custom_taxonomy'])) {
            $taxonomy = sanitize_key($settings['custom_taxonomy']);
        } elseif ($filter_type === 'attribute' && !empty($settings['attribute'])) {
            $taxonomy = sanitize_key($settings['attribute']);
        } elseif (!empty($recommendation['details']['taxonomy'])) {
            $taxonomy = sanitize_key($recommendation['details']['taxonomy']);
        }
        return $this->is_brand_taxonomy($taxonomy) ? $taxonomy : '';
    }

    protected function is_brand_taxonomy($taxonomy) {
        $taxonomies = apply_filters('brapf_one_click_brand_taxonomies', array(
            'product_brand',
            'berocket_brand',
            'pwb-brand',
            'yith_product_brand',
            'pa_brand',
        ));
        return in_array(sanitize_key($taxonomy), (array) $taxonomies, true);
    }

    protected function is_image_style($settings) {
        if (!is_array($settings) || empty($settings['style'])) {
            return false;
        }
        return in_array(sanitize_key($settings['style']), array('image', 'image_woborder'), true);
    }

    protected function get_image_meta_keys($taxonomy) {
        $keys = array(
            'berocket_brand' => array('brand_image_url'),
            'product_brand' => array('thumbnail_id', 'brand_image_url'),
            'pwb-brand' => array('pwb_brand_image', 'thumbnail_id'),
            'yith_product_brand' => array('yith_product_brand_logo', 'thumbnail_id'),
            'pa_brand' => array('thumbnail_id', 'brand_image_url', 'image_id'),
        );
        $fallback = array('thumbnail_id', 'brand_image_url', 'image_id', 'logo_id', 'logo', 'image');
        $keys = apply_filters('brapf_brand_image_meta_keys', $keys, $taxonomy);
        return isset($keys[$taxonomy]) && is_array($keys[$taxonomy]) ? $keys[$taxonomy] : $fallback;
    }

    protected function normalize_image_value($value) {
        if (is_array($value)) {
            foreach (array('url', 'src', 'image_url') as $key) {
                if (!empty($value[$key])) {
                    return $this->normalize_image_value($value[$key]);
                }
            }
            foreach (array('id', 'attachment_id', 'thumbnail_id') as $key) {
                if (!empty($value[$key])) {
                    return $this->normalize_image_value($value[$key]);
                }
            }
            return '';
        }
        if (is_numeric($value) && absint($value)) {
            $value = wp_get_attachment_url(absint($value));
        }
        return is_string($value) && $value !== '' ? esc_url_raw($value) : '';
    }

    /** A manually set image (even an empty one) must always win over a logo fallback. */
    protected function has_stored_term_value($term_id, $meta_key) {
        $values = get_metadata('berocket_term', absint($term_id), $meta_key, false);
        return is_array($values) && count($values) > 0;
    }
}
new BeRocket_AAPF_Brand_Image_Filter_Defaults();
