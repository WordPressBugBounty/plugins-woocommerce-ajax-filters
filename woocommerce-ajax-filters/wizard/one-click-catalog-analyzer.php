<?php
/**
 * Lightweight catalog snapshot for the intelligent one-click wizard.
 *
 * Product facts come from WooCommerce's wc_product_meta_lookup table; term
 * facts come from WordPress taxonomy counts. This class must never query or
 * iterate over wp_postmeta while analysing a catalog.
 */
class BeRocket_AAPF_One_Click_Catalog_Analyzer {
    const SCHEMA_VERSION = 1;

    public function analyze() {
        $snapshot = $this->get_empty_snapshot();
        if (!function_exists('wc_get_attribute_taxonomies')) {
            $snapshot['status'] = 'unavailable';
            $snapshot['errors'][] = 'woocommerce_unavailable';
            return $snapshot;
        }

        global $wpdb;
        $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
        if (!$this->table_exists($lookup_table)) {
            $snapshot['status'] = 'unavailable';
            $snapshot['errors'][] = 'product_lookup_missing';
            return $snapshot;
        }

        $snapshot['catalog']['products'] = $this->get_product_summary($lookup_table);
        $snapshot['catalog']['stock_statuses'] = $this->get_stock_status_counts($lookup_table);
        $snapshot['catalog']['categories'] = $this->get_taxonomy_summary('product_cat');
        $snapshot['catalog']['tags'] = $this->get_taxonomy_summary('product_tag', false);
        $snapshot['catalog']['attributes'] = $this->get_attribute_summaries();
        $snapshot['catalog']['brand_taxonomies'] = $this->get_brand_taxonomy_summaries();
        $snapshot['status'] = 'ready';
        return $snapshot;
    }

    protected function get_empty_snapshot() {
        return array(
            'schema_version' => self::SCHEMA_VERSION,
            'status' => 'pending',
            'source' => 'woocommerce_lookup_and_taxonomy_counts',
            'generated_at' => current_time('mysql', true),
            'errors' => array(),
            'catalog' => array(
                'products' => array(
                    'count' => 0,
                    'lookup_count' => 0,
                    'unindexed_count' => 0,
                    'price' => array(
                        'priced_product_count' => 0,
                        'min' => null,
                        'max' => null,
                        'has_range' => false,
                    ),
                    'sale_product_count' => 0,
                    'reviews' => array(
                        'rated_product_count' => 0,
                        'rating_count' => 0,
                        'average_rating' => 0.0,
                    ),
                ),
                'stock_statuses' => array(),
                'categories' => $this->get_empty_taxonomy_summary('product_cat'),
                'tags' => $this->get_empty_taxonomy_summary('product_tag'),
                'attributes' => array(),
                'brand_taxonomies' => array(),
            ),
        );
    }

    protected function table_exists($table) {
        global $wpdb;
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
        return $wpdb->get_var($query) === $table;
    }

    /** One aggregate query for product count, price, sale and review facts. */
    protected function get_product_summary($lookup_table) {
        global $wpdb;
        $sql = "SELECT
                COUNT(products.ID) AS product_count,
                SUM(CASE WHEN lookup_data.product_id IS NOT NULL THEN 1 ELSE 0 END) AS lookup_count,
                SUM(CASE WHEN lookup_data.min_price IS NOT NULL AND lookup_data.max_price IS NOT NULL THEN 1 ELSE 0 END) AS priced_product_count,
                MIN(lookup_data.min_price) AS min_price,
                MAX(lookup_data.max_price) AS max_price,
                SUM(CASE WHEN lookup_data.onsale = 1 THEN 1 ELSE 0 END) AS sale_product_count,
                SUM(CASE WHEN lookup_data.rating_count > 0 THEN 1 ELSE 0 END) AS rated_product_count,
                SUM(COALESCE(lookup_data.rating_count, 0)) AS rating_count,
                SUM(COALESCE(lookup_data.average_rating, 0) * COALESCE(lookup_data.rating_count, 0)) AS rating_total
            FROM {$wpdb->posts} AS products
            LEFT JOIN {$lookup_table} AS lookup_data ON lookup_data.product_id = products.ID
            WHERE products.post_type = 'product' AND products.post_status = 'publish'";
        $row = $wpdb->get_row($sql, ARRAY_A);
        if (!is_array($row)) {
            $row = array();
        }

        $count = absint($this->get_row_value($row, 'product_count'));
        $lookup_count = absint($this->get_row_value($row, 'lookup_count'));
        $priced_product_count = absint($this->get_row_value($row, 'priced_product_count'));
        $min_price = $priced_product_count ? (float)$this->get_row_value($row, 'min_price') : null;
        $max_price = $priced_product_count ? (float)$this->get_row_value($row, 'max_price') : null;
        $rating_count = absint($this->get_row_value($row, 'rating_count'));
        $rating_total = (float)$this->get_row_value($row, 'rating_total');

        return array(
            'count' => $count,
            'lookup_count' => $lookup_count,
            'unindexed_count' => max(0, $count - $lookup_count),
            'price' => array(
                'priced_product_count' => $priced_product_count,
                'min' => $min_price,
                'max' => $max_price,
                'has_range' => ($min_price !== null && $max_price !== null && $min_price < $max_price),
            ),
            'sale_product_count' => absint($this->get_row_value($row, 'sale_product_count')),
            'reviews' => array(
                'rated_product_count' => absint($this->get_row_value($row, 'rated_product_count')),
                'rating_count' => $rating_count,
                'average_rating' => $rating_count ? round($rating_total / $rating_count, 2) : 0.0,
            ),
        );
    }

    /** A tiny grouped lookup query; it does not inspect product meta. */
    protected function get_stock_status_counts($lookup_table) {
        global $wpdb;
        $sql = "SELECT
                COALESCE(NULLIF(lookup_data.stock_status, ''), 'unknown') AS stock_status,
                COUNT(products.ID) AS product_count
            FROM {$wpdb->posts} AS products
            LEFT JOIN {$lookup_table} AS lookup_data ON lookup_data.product_id = products.ID
            WHERE products.post_type = 'product' AND products.post_status = 'publish'
            GROUP BY stock_status";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $statuses = array();
        if (!is_array($rows)) {
            return $statuses;
        }
        foreach ($rows as $row) {
            $status = isset($row['stock_status']) ? sanitize_key($row['stock_status']) : 'unknown';
            $statuses[$status ? $status : 'unknown'] = absint($this->get_row_value($row, 'product_count'));
        }
        ksort($statuses);
        return $statuses;
    }

    protected function get_attribute_summaries() {
        $attributes = array();
        $registered_attributes = wc_get_attribute_taxonomies();
        if (!is_array($registered_attributes)) {
            return $attributes;
        }
        foreach ($registered_attributes as $attribute) {
            if (empty($attribute->attribute_name)) {
                continue;
            }
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $summary = $this->get_taxonomy_summary($taxonomy);
            $summary['attribute_id'] = isset($attribute->attribute_id) ? absint($attribute->attribute_id) : 0;
            $summary['label'] = isset($attribute->attribute_label) ? $attribute->attribute_label : $taxonomy;
            $attributes[$taxonomy] = $summary;
        }
        ksort($attributes);
        return $attributes;
    }

    /**
     * Woo Brands and other brand plugins normally register a product taxonomy.
     * Keep it separate from pa_brand attributes so ranking can prefer either
     * source without creating duplicate Brand recommendations.
     */
    protected function get_brand_taxonomy_summaries() {
        if (!function_exists('get_taxonomies')) {
            return array();
        }
        $known_taxonomies = apply_filters('brapf_one_click_brand_taxonomies', array(
            'product_brand',
            'berocket_brand',
            'pwb-brand',
            'yith_product_brand',
        ));
        $taxonomies = get_taxonomies(array(), 'objects');
        $brands = array();
        if (!is_array($taxonomies)) {
            return $brands;
        }
        foreach ($taxonomies as $taxonomy => $taxonomy_object) {
            if (strpos($taxonomy, 'pa_') === 0 || empty($taxonomy_object->object_type)
                || !in_array('product', (array)$taxonomy_object->object_type, true)) {
                continue;
            }
            $label = !empty($taxonomy_object->label) ? $taxonomy_object->label : '';
            $singular_label = !empty($taxonomy_object->labels->singular_name) ? $taxonomy_object->labels->singular_name : '';
            $search_text = strtolower($taxonomy . ' ' . $label . ' ' . $singular_label);
            if (!in_array($taxonomy, $known_taxonomies, true)
                && strpos($search_text, 'brand') === false
                && strpos($search_text, 'manufacturer') === false) {
                continue;
            }
            $summary = $this->get_taxonomy_summary($taxonomy);
            $summary['label'] = $label ? $label : $taxonomy;
            $brands[$taxonomy] = $summary;
        }
        ksort($brands);
        return $brands;
    }

    /**
     * get_terms() reads term_taxonomy counts maintained by WordPress. Values
     * are normalized so the ranking stage does not depend on WP_Term objects.
     */
    protected function get_taxonomy_summary($taxonomy, $include_values = true) {
        $summary = $this->get_empty_taxonomy_summary($taxonomy);
        if (!taxonomy_exists($taxonomy)) {
            return $summary;
        }
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
        ));
        if (is_wp_error($terms) || !is_array($terms)) {
            return $summary;
        }
        $summary['count'] = count($terms);
        foreach ($terms as $term) {
            $term_count = isset($term->count) ? absint($term->count) : 0;
            $summary['assigned_product_count'] += $term_count;
            if ($include_values) {
                $summary['values'][] = array(
                    'id' => isset($term->term_id) ? absint($term->term_id) : 0,
                    'name' => isset($term->name) ? $term->name : '',
                    'slug' => isset($term->slug) ? $term->slug : '',
                    'parent' => isset($term->parent) ? absint($term->parent) : 0,
                    'product_count' => $term_count,
                );
            }
        }
        return $summary;
    }

    protected function get_empty_taxonomy_summary($taxonomy) {
        return array(
            'taxonomy' => $taxonomy,
            'count' => 0,
            'assigned_product_count' => 0,
            'values' => array(),
        );
    }

    protected function get_row_value($row, $key) {
        return isset($row[$key]) && $row[$key] !== null ? $row[$key] : 0;
    }
}
