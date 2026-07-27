<?php
/**
 * Turns catalog and attribute-analysis snapshots into the small, ranked list
 * shown by the one-click setup. It is deterministic and query-free.
 */
class BeRocket_AAPF_One_Click_Recommendation_Ranker {
    const SCHEMA_VERSION = 1;

    public function rank($catalog_snapshot, $attribute_analysis, $capability = null) {
        $result = array(
            'schema_version' => self::SCHEMA_VERSION,
            'status' => 'ready',
            'ranked_at' => current_time('mysql', true),
            'recommendations' => array(),
            'candidates' => array(),
            'errors' => array(),
        );
        $product_count = $this->get_product_count($catalog_snapshot);
        if (!$product_count) {
            $result['status'] = 'unavailable';
            $result['errors'][] = 'catalog_has_no_products';
            return $result;
        }
        $config = $this->get_config();
        $candidates = array_merge(
            $this->get_price_candidate($catalog_snapshot, $capability),
            $this->get_category_candidate($catalog_snapshot, $capability),
            $this->get_brand_candidates($catalog_snapshot, $attribute_analysis, $capability),
            $this->get_attribute_candidates($attribute_analysis, $capability),
            $this->get_availability_candidate($catalog_snapshot, $capability),
            $this->get_sale_candidate($catalog_snapshot, $capability),
            $this->get_rating_candidate($catalog_snapshot, $capability),
            $this->get_tags_candidate($catalog_snapshot, $capability)
        );
        $filtered_candidates = apply_filters(
            'brapf_one_click_recommendation_candidates',
            $candidates,
            $catalog_snapshot,
            $attribute_analysis,
            $capability,
            $config
        );
        $result['candidates'] = $this->sort_candidates(is_array($filtered_candidates) ? $filtered_candidates : $candidates);
        $recommendations = $this->select_recommendations($result['candidates'], $config);
        $filtered_recommendations = apply_filters(
            'brapf_one_click_recommendations',
            $recommendations,
            $result['candidates'],
            $catalog_snapshot,
            $attribute_analysis,
            $capability,
            $config
        );
        $result['recommendations'] = is_array($filtered_recommendations) ? $filtered_recommendations : $recommendations;
        return $result;
    }

    protected function get_config() {
        $config = array(
            'max_additional_filters' => 5,
            'category_minimum_count' => 2,
            'rating_minimum_coverage' => 0.20,
            'rating_minimum_products' => 20,
            'sale_minimum_ratio' => 0.05,
            'sale_maximum_ratio' => 0.50,
            'tags_minimum_coverage' => 0.30,
            'tags_minimum_count' => 2,
            'tags_maximum_count' => 30,
        );
        return apply_filters('brapf_one_click_recommendation_ranking_config', $config);
    }

    protected function get_price_candidate($snapshot, $capability) {
        $price = isset($snapshot['catalog']['products']['price']) ? $snapshot['catalog']['products']['price'] : array();
        if (!$this->supports('price', $capability) || empty($price['has_range'])) {
            return array();
        }
        return array($this->make_candidate(
            'price',
            'price',
            100,
            array('price_range'),
            BeRocket_AAPF_One_Click_Capabilities::get_filter_preset('price', array(), $capability),
            array('min_price' => (float)$price['min'], 'max_price' => (float)$price['max']),
            true
        ));
    }

    protected function get_category_candidate($snapshot, $capability) {
        $categories = isset($snapshot['catalog']['categories']) ? $snapshot['catalog']['categories'] : array();
        $count = isset($categories['count']) ? absint($categories['count']) : 0;
        if (!$this->supports('categories', $capability) || $count < $this->get_config()['category_minimum_count']) {
            return array();
        }
        return array($this->make_candidate(
            'categories',
            'categories',
            min(98, 90 + $count),
            array('multiple_meaningful_categories'),
            BeRocket_AAPF_One_Click_Capabilities::get_filter_preset('categories', array('category_count' => $count), $capability),
            array('category_count' => $count)
        ));
    }

    protected function get_brand_candidates($snapshot, $attribute_analysis, $capability) {
        if (!$this->supports('brand', $capability)) {
            return array();
        }
        $brands = isset($snapshot['catalog']['brand_taxonomies']) && is_array($snapshot['catalog']['brand_taxonomies'])
            ? $snapshot['catalog']['brand_taxonomies']
            : array();

        // Keep one canonical Brand source.  A populated BeRocket Brands
        // taxonomy is authoritative, but an installed-yet-empty taxonomy must
        // not hide WooCommerce Brands or another usable source.
        if (BeRocket_AAPF_One_Click_Capabilities::is_berocket_brands_active()) {
            $brand = isset($brands['berocket_brand']) ? $brands['berocket_brand'] : array();
            $count = isset($brand['count']) ? absint($brand['count']) : 0;
            if ($count >= 2) {
                return array($this->make_brand_taxonomy_candidate(
                    'berocket_brand',
                    $count,
                    min(99, 95 + min(4, $count)),
                    array('berocket_brands_active', 'preferred_brand_provider')
                ));
            }
        }

        // WooCommerce Brands is the first fallback whenever BeRocket Brands
        // is unavailable or has no meaningful terms.
        $woocommerce_brand = isset($brands['product_brand']) ? $brands['product_brand'] : array();
        $woocommerce_count = isset($woocommerce_brand['count']) ? absint($woocommerce_brand['count']) : 0;
        if ($woocommerce_count >= 2) {
            return array($this->make_brand_taxonomy_candidate(
                'product_brand',
                $woocommerce_count,
                min(98, 94 + min(4, $woocommerce_count)),
                array('woocommerce_brand_taxonomy', 'preferred_brand_provider')
            ));
        }

        // Only after both primary providers are unusable may a recognised
        // third-party taxonomy be used.  The sorted snapshot makes this stable.
        foreach ($brands as $taxonomy => $brand) {
            $taxonomy = sanitize_key($taxonomy);
            if (in_array($taxonomy, array('berocket_brand', 'product_brand'), true)) {
                continue;
            }
            $count = isset($brand['count']) ? absint($brand['count']) : 0;
            if ($count < 2) {
                continue;
            }
            return array($this->make_brand_taxonomy_candidate(
                $taxonomy,
                $count,
                min(97, 93 + min(4, $count)),
                array('recognized_third_party_brand_taxonomy', 'brand_fallback')
            ));
        }

        // pa_brand is an attribute, not a primary brand provider.  Use it only
        // when no populated brand taxonomy is available, preferring its stable
        // conventional taxonomy name over other semantic brand attributes.
        $attributes = isset($attribute_analysis['candidates']) && is_array($attribute_analysis['candidates'])
            ? $attribute_analysis['candidates']
            : array();
        if (isset($attributes['pa_brand'])) {
            $attributes = array('pa_brand' => $attributes['pa_brand']) + $attributes;
        }
        foreach ($attributes as $taxonomy => $attribute) {
            if (empty($attribute['eligible']) || empty($attribute['semantic'])
                || $attribute['semantic']['kind'] !== 'brand') {
                continue;
            }
            return array($this->make_candidate(
                'brand:' . $taxonomy,
                'brand',
                96,
                array('brand_attribute_fallback', 'high_semantic_priority'),
                array('widget_type' => 'filter', 'filter_type' => 'attribute', 'attribute' => $taxonomy, 'style' => 'grey-check'),
                array('taxonomy' => $taxonomy, 'attribute_metrics' => $attribute)
            ));
        }
        return array();
    }

    protected function make_brand_taxonomy_candidate($taxonomy, $count, $score, $reasons) {
        $filter_definition = $taxonomy === 'berocket_brand'
            ? array('widget_type' => 'filter', 'filter_type' => 'berocket_brand', 'style' => 'grey-check')
            : array('widget_type' => 'filter', 'filter_type' => 'custom_taxonomy', 'custom_taxonomy' => $taxonomy, 'style' => 'grey-check');
        return $this->make_candidate(
            'brand:' . $taxonomy,
            'brand',
            $score,
            $reasons,
            $filter_definition,
            array('taxonomy' => $taxonomy, 'value_count' => $count)
        );
    }

    protected function get_attribute_candidates($attribute_analysis, $capability) {
        if (!$this->supports('attribute', $capability) || empty($attribute_analysis['candidates'])
            || !is_array($attribute_analysis['candidates'])) {
            return array();
        }
        $candidates = array();
        foreach ($attribute_analysis['candidates'] as $taxonomy => $attribute) {
            if (empty($attribute['eligible']) || empty($attribute['semantic'])
                || $attribute['semantic']['kind'] === 'brand') {
                continue;
            }
            $score = $this->get_attribute_score($attribute);
            $candidates[] = $this->make_candidate(
                'attribute:' . $taxonomy,
                'attribute:' . $taxonomy,
                $score,
                array('eligible_global_attribute', 'coverage_and_distribution'),
                array('widget_type' => 'filter', 'filter_type' => 'attribute', 'attribute' => $taxonomy, 'style' => 'grey-check'),
                array('taxonomy' => $taxonomy, 'attribute_metrics' => $attribute)
            );
        }
        return $candidates;
    }

    /** Implements the agreed 40% coverage / 25% values / 20% distribution / 15% semantic weighting. */
    protected function get_attribute_score($attribute) {
        $coverage = isset($attribute['coverage']) ? (float)$attribute['coverage'] : 0;
        $value_count = isset($attribute['value_count']) ? absint($attribute['value_count']) : 0;
        $distribution = isset($attribute['distribution']['normalized_entropy']) ? (float)$attribute['distribution']['normalized_entropy'] : 0;
        $semantic = isset($attribute['semantic']['priority']) ? (float)$attribute['semantic']['priority'] : 0;
        return round(100 * (
            0.40 * min(1, $coverage)
            + 0.25 * $this->get_value_count_score($value_count)
            + 0.20 * min(1, $distribution)
            + 0.15 * min(1, $semantic)
        ), 2);
    }

    protected function get_value_count_score($count) {
        if ($count < 2 || $count > 30) {
            return 0;
        }
        if ($count <= 10) {
            return 0.60 + (0.40 * ($count - 2) / 8);
        }
        return 1 - (0.50 * ($count - 10) / 20);
    }

    protected function get_availability_candidate($snapshot, $capability) {
        if (!$this->supports('availability', $capability)) {
            return array();
        }
        $statuses = isset($snapshot['catalog']['stock_statuses']) && is_array($snapshot['catalog']['stock_statuses'])
            ? array_filter($snapshot['catalog']['stock_statuses'])
            : array();
        if (count($statuses) < 2) {
            return array();
        }
        $total = array_sum($statuses);
        $largest_share = $total ? max($statuses) / $total : 1;
        return array($this->make_candidate(
            'availability',
            'availability',
            round(65 + (20 * (1 - $largest_share)), 2),
            array('mixed_stock_statuses'),
            BeRocket_AAPF_One_Click_Capabilities::get_filter_preset('availability', array(), $capability),
            array('stock_statuses' => $statuses)
        ));
    }

    protected function get_sale_candidate($snapshot, $capability) {
        if (!$this->supports('on_sale', $capability)) {
            return array();
        }
        $product_count = $this->get_product_count($snapshot);
        $sale_count = isset($snapshot['catalog']['products']['sale_product_count'])
            ? absint($snapshot['catalog']['products']['sale_product_count'])
            : 0;
        $ratio = $product_count ? $sale_count / $product_count : 0;
        $config = $this->get_config();
        if ($ratio < $config['sale_minimum_ratio'] || $ratio > $config['sale_maximum_ratio']) {
            return array();
        }
        return array($this->make_candidate(
            'on_sale',
            'on_sale',
            round(55 + (20 * min(1, $ratio / $config['sale_maximum_ratio'])), 2),
            array('meaningful_sale_share'),
            BeRocket_AAPF_One_Click_Capabilities::get_filter_preset('on_sale', array(), $capability),
            array('sale_product_count' => $sale_count, 'sale_ratio' => round($ratio, 4))
        ));
    }

    protected function get_rating_candidate($snapshot, $capability) {
        if (!$this->supports('rating', $capability)) {
            return array();
        }
        $product_count = $this->get_product_count($snapshot);
        $reviews = isset($snapshot['catalog']['products']['reviews']) ? $snapshot['catalog']['products']['reviews'] : array();
        $rated_count = isset($reviews['rated_product_count']) ? absint($reviews['rated_product_count']) : 0;
        $coverage = $product_count ? $rated_count / $product_count : 0;
        $config = $this->get_config();
        if ($rated_count < $config['rating_minimum_products'] || $coverage < $config['rating_minimum_coverage']) {
            return array();
        }
        return array($this->make_candidate(
            'rating',
            'rating',
            round(55 + (25 * min(1, $coverage)), 2),
            array('sufficient_product_reviews'),
            array('widget_type' => 'filter', 'filter_type' => '_rating', 'style' => 'grey-check'),
            array('rated_product_count' => $rated_count, 'coverage' => round($coverage, 4))
        ));
    }

    protected function get_tags_candidate($snapshot, $capability) {
        if (!$this->supports('tags', $capability)) {
            return array();
        }
        $tags = isset($snapshot['catalog']['tags']) ? $snapshot['catalog']['tags'] : array();
        $count = isset($tags['count']) ? absint($tags['count']) : 0;
        $coverage = $this->get_product_count($snapshot)
            ? min(1, (isset($tags['assigned_product_count']) ? absint($tags['assigned_product_count']) : 0) / $this->get_product_count($snapshot))
            : 0;
        $config = $this->get_config();
        if ($count < $config['tags_minimum_count'] || $count > $config['tags_maximum_count']
            || $coverage < $config['tags_minimum_coverage']) {
            return array();
        }
        return array($this->make_candidate(
            'tags',
            'tags',
            round(30 + (10 * min(1, $coverage)), 2),
            array('structured_tags', 'lowest_priority'),
            array('widget_type' => 'filter', 'filter_type' => 'tag', 'style' => 'grey-check'),
            array('tag_count' => $count, 'coverage' => round($coverage, 4))
        ));
    }

    protected function make_candidate($key, $family, $score, $reasons, $filter_definition, $details, $pinned = false) {
        return array(
            'key' => $key,
            'family' => $family,
            'score' => (float)$score,
            'reasons' => $reasons,
            'filter_definition' => $filter_definition,
            'details' => $details,
            'pinned' => (bool)$pinned,
        );
    }

    protected function sort_candidates($candidates) {
        usort($candidates, function($first, $second) {
            if ($first['pinned'] !== $second['pinned']) {
                return $first['pinned'] ? -1 : 1;
            }
            if ($first['score'] == $second['score']) {
                return strcmp($first['key'], $second['key']);
            }
            return ($first['score'] > $second['score']) ? -1 : 1;
        });
        return $candidates;
    }

    /** Keep Price when valid and select no more than five additional families. */
    protected function select_recommendations($candidates, $config) {
        $selected = array();
        $selected_families = array();
        $additional_count = 0;
        foreach ($candidates as $candidate) {
            if (isset($selected_families[$candidate['family']])) {
                continue;
            }
            if (!$candidate['pinned'] && $additional_count >= $config['max_additional_filters']) {
                continue;
            }
            $selected[] = $candidate;
            $selected_families[$candidate['family']] = true;
            if (!$candidate['pinned']) {
                $additional_count++;
            }
        }
        return $selected;
    }

    protected function supports($feature, $capability) {
        return BeRocket_AAPF_One_Click_Capabilities::supports($feature, $capability);
    }

    protected function get_product_count($snapshot) {
        return !empty($snapshot['catalog']['products']['count'])
            ? absint($snapshot['catalog']['products']['count'])
            : 0;
    }
}
