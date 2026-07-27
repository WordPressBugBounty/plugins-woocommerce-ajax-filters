<?php
/**
 * Cache boundary for the one-click catalog analysis.
 *
 * The catalog snapshot is the only expensive part. Attribute analysis and
 * ranking are also stored with it so opening the wizard again performs no
 * catalog queries or repeat calculations while the cache is valid.
 */
class BeRocket_AAPF_One_Click_Analysis_Cache {
    const CACHE_VERSION = 1;
    const TRANSIENT_NAME = 'br_aapf_one_click_analysis_v1';
    const DEFAULT_TTL = 21600;

    public function __construct() {
        add_action('woocommerce_new_product', array($this, 'invalidate_product'));
        add_action('woocommerce_update_product', array($this, 'invalidate_product'));
        add_action('woocommerce_delete_product', array($this, 'invalidate_product'));
        add_action('woocommerce_product_set_stock', array($this, 'invalidate_product'));
        add_action('woocommerce_product_set_stock_status', array($this, 'invalidate_product'));
        add_action('woocommerce_product_object_updated_props', array($this, 'invalidate_product'), 10, 2);
        add_action('set_object_terms', array($this, 'invalidate_product_terms'), 10, 4);
        add_action('created_term', array($this, 'invalidate_term'), 10, 3);
        add_action('edited_term', array($this, 'invalidate_term'), 10, 3);
        add_action('delete_term', array($this, 'invalidate_term'), 10, 3);
        add_action('woocommerce_attribute_added', array($this, 'invalidate_attribute'));
        add_action('woocommerce_attribute_updated', array($this, 'invalidate_attribute'));
        add_action('woocommerce_attribute_deleted', array($this, 'invalidate_attribute'));
    }

    /**
     * Return cached analysis or generate it once. $force is reserved for the
     * future Advanced setup / manual refresh action.
     */
    public function get_analysis($capability = null, $force = false) {
        if (!$force) {
            $cached = $this->get_cached_analysis($capability);
            if ($cached !== false) {
                return $cached;
            }
        }
        $tier = BeRocket_AAPF_One_Click_Capabilities::get_tier($capability);
        $cache = $force ? false : $this->get_cache();
        $snapshot_cache_hit = is_array($cache) && !empty($cache['snapshot']);
        if (!$snapshot_cache_hit) {
            $cache = $this->build_cache();
        }

        $snapshot_hash = $cache['snapshot_hash'];
        $recommendation_context = $this->get_recommendation_context($tier, $capability);
        $recommendation_context_hash = $this->hash($recommendation_context);
        $recommendation_cache_hit = isset($cache['recommendations'][$tier])
            && $cache['recommendations'][$tier]['snapshot_hash'] === $snapshot_hash
            && $cache['recommendations'][$tier]['context_hash'] === $recommendation_context_hash;
        if (!$recommendation_cache_hit) {
            $attribute_analysis = (new BeRocket_AAPF_One_Click_Attribute_Analyzer())->analyze($cache['snapshot']);
            $ranking = (new BeRocket_AAPF_One_Click_Recommendation_Ranker())->rank(
                $cache['snapshot'],
                $attribute_analysis,
                $capability
            );
            $cache['recommendations'][$tier] = array(
                'snapshot_hash' => $snapshot_hash,
                'context_hash' => $recommendation_context_hash,
                'recommendation_hash' => $this->hash($ranking['recommendations']),
                'created_at' => current_time('mysql', true),
                'attribute_analysis' => $attribute_analysis,
                'ranking' => $ranking,
            );
            $this->set_cache($cache);
        }
        $recommendation = $cache['recommendations'][$tier];
        return array(
            'cache_hit' => $snapshot_cache_hit && $recommendation_cache_hit,
            'snapshot_cache_hit' => $snapshot_cache_hit,
            'recommendation_cache_hit' => $recommendation_cache_hit,
            'expires_at' => $cache['expires_at'],
            'snapshot' => $cache['snapshot'],
            'snapshot_hash' => $snapshot_hash,
            'attribute_analysis' => $recommendation['attribute_analysis'],
            'ranking' => $recommendation['ranking'],
            'recommendation_hash' => $recommendation['recommendation_hash'],
        );
    }

    /** Read-only cache lookup used by the wizard progress screen. */
    public function get_cached_analysis($capability = null) {
        $cache = $this->get_cache();
        if (!is_array($cache) || empty($cache['snapshot_hash'])) {
            return false;
        }
        $tier = BeRocket_AAPF_One_Click_Capabilities::get_tier($capability);
        $context_hash = $this->hash($this->get_recommendation_context($tier, $capability));
        if (empty($cache['recommendations'][$tier])
            || $cache['recommendations'][$tier]['snapshot_hash'] !== $cache['snapshot_hash']
            || $cache['recommendations'][$tier]['context_hash'] !== $context_hash) {
            return false;
        }
        $recommendation = $cache['recommendations'][$tier];
        return array(
            'cache_hit' => true,
            'snapshot_cache_hit' => true,
            'recommendation_cache_hit' => true,
            'expires_at' => $cache['expires_at'],
            'snapshot' => $cache['snapshot'],
            'snapshot_hash' => $cache['snapshot_hash'],
            'attribute_analysis' => $recommendation['attribute_analysis'],
            'ranking' => $recommendation['ranking'],
            'recommendation_hash' => $recommendation['recommendation_hash'],
        );
    }

    /** True when a profile change can be re-ranked without a catalog scan. */
    public function has_cached_snapshot() {
        $cache = $this->get_cache();
        return is_array($cache) && !empty($cache['snapshot_hash']) && !empty($cache['snapshot']);
    }

    public function invalidate($reason = 'manual') {
        delete_transient(self::TRANSIENT_NAME);
        do_action('brapf_one_click_catalog_analysis_invalidated', sanitize_key($reason));
    }

    public function invalidate_product() {
        $this->invalidate('product_changed');
    }

    public function invalidate_attribute() {
        $this->invalidate('attribute_schema_changed');
    }

    public function invalidate_product_terms($object_id, $terms, $tt_ids, $taxonomy) {
        if (!$this->is_relevant_taxonomy($taxonomy)) {
            return;
        }
        $post_type = get_post_type($object_id);
        if (in_array($post_type, array('product', 'product_variation'), true)) {
            $this->invalidate('product_terms_changed');
        }
    }

    public function invalidate_term($term_id, $tt_id, $taxonomy) {
        if ($this->is_relevant_taxonomy($taxonomy)) {
            $this->invalidate('taxonomy_changed');
        }
    }

    protected function build_cache() {
        $snapshot = (new BeRocket_AAPF_One_Click_Catalog_Analyzer())->analyze();
        return array(
            'cache_version' => self::CACHE_VERSION,
            'created_at' => current_time('mysql', true),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $this->get_ttl()),
            'snapshot' => $snapshot,
            'snapshot_hash' => $this->get_snapshot_hash($snapshot),
            'recommendations' => array(),
        );
    }

    protected function get_cache() {
        $cache = get_transient(self::TRANSIENT_NAME);
        if (!is_array($cache) || empty($cache['cache_version'])
            || (int)$cache['cache_version'] !== self::CACHE_VERSION) {
            return false;
        }
        return $cache;
    }

    protected function set_cache($cache) {
        set_transient(self::TRANSIENT_NAME, $cache, $this->get_ttl());
    }

    protected function get_ttl() {
        return max(60, (int)apply_filters('brapf_one_click_analysis_cache_ttl', self::DEFAULT_TTL));
    }

    /** The hash intentionally excludes generated_at and cache timestamps. */
    protected function get_snapshot_hash($snapshot) {
        return $this->hash(array(
            'schema_version' => isset($snapshot['schema_version']) ? $snapshot['schema_version'] : 0,
            'status' => isset($snapshot['status']) ? $snapshot['status'] : '',
            'catalog' => isset($snapshot['catalog']) ? $snapshot['catalog'] : array(),
        ));
    }

    protected function get_recommendation_context($tier, $capability) {
        $capability_value = ($capability === null)
            ? BeRocket_AAPF_One_Click_Capabilities::get_capability_value()
            : (int)$capability;
        return apply_filters('brapf_one_click_recommendation_cache_context', array(
            'cache_version' => self::CACHE_VERSION,
            'tier' => $tier,
            'capability' => $capability_value,
            'capability_map' => BeRocket_AAPF_One_Click_Capabilities::get_map($capability),
        ), $tier, $capability);
    }

    protected function hash($data) {
        return hash('sha256', wp_json_encode($data));
    }

    protected function is_relevant_taxonomy($taxonomy) {
        if ($taxonomy === 'product_cat' || $taxonomy === 'product_tag' || strpos($taxonomy, 'pa_') === 0) {
            return true;
        }
        $brand_taxonomies = apply_filters('brapf_one_click_brand_taxonomies', array(
            'product_brand',
            'berocket_brand',
            'pwb-brand',
            'yith_product_brand',
        ));
        return in_array($taxonomy, $brand_taxonomies, true);
    }
}
new BeRocket_AAPF_One_Click_Analysis_Cache();
