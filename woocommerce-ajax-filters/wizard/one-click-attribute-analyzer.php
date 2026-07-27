<?php
/**
 * Scores the usefulness of global attributes from a catalog snapshot.
 *
 * This class is deliberately query-free: it only consumes the normalized
 * result of BeRocket_AAPF_One_Click_Catalog_Analyzer.
 */
class BeRocket_AAPF_One_Click_Attribute_Analyzer {
    const SCHEMA_VERSION = 1;

    public function analyze($catalog_snapshot) {
        $result = array(
            'schema_version' => self::SCHEMA_VERSION,
            'status' => 'ready',
            'analyzed_at' => current_time('mysql', true),
            'thresholds' => $this->get_thresholds(),
            'candidates' => array(),
            'eligible_taxonomies' => array(),
            'errors' => array(),
        );
        $product_count = $this->get_product_count($catalog_snapshot);
        if (!$product_count) {
            $result['status'] = 'unavailable';
            $result['errors'][] = 'catalog_has_no_products';
            return $result;
        }
        $attributes = isset($catalog_snapshot['catalog']['attributes']) && is_array($catalog_snapshot['catalog']['attributes'])
            ? $catalog_snapshot['catalog']['attributes']
            : array();
        if (empty($attributes)) {
            return $result;
        }
        foreach ($attributes as $taxonomy => $attribute) {
            $candidate = $this->analyze_attribute($taxonomy, $attribute, $product_count, $result['thresholds']);
            $result['candidates'][$taxonomy] = $candidate;
            if ($candidate['eligible']) {
                $result['eligible_taxonomies'][] = $taxonomy;
            }
        }
        return $result;
    }

    protected function get_thresholds() {
        $thresholds = array(
            // 45% is the midpoint of the intended 40–50% coverage range.
            'minimum_coverage' => 0.45,
            'minimum_value_count' => 2,
            'maximum_value_count' => 30,
            // Values are effectively IDs when they cover at least 80% of the assigned products.
            'nearly_unique_value_ratio' => 0.80,
            'maximum_dominant_value_share' => 0.95,
        );
        return apply_filters('brapf_one_click_attribute_analyzer_thresholds', $thresholds);
    }

    protected function analyze_attribute($taxonomy, $attribute, $product_count, $thresholds) {
        $values = isset($attribute['values']) && is_array($attribute['values']) ? $attribute['values'] : array();
        $value_counts = array();
        foreach ($values as $value) {
            $count = isset($value['product_count']) ? absint($value['product_count']) : 0;
            if ($count) {
                $value_counts[] = $count;
            }
        }
        // The taxonomy summary remains useful if a caller omitted its values.
        $assignment_count = array_sum($value_counts);
        if (!$assignment_count && isset($attribute['assigned_product_count'])) {
            $assignment_count = absint($attribute['assigned_product_count']);
        }
        $value_count = count($value_counts);
        if (!$value_count && isset($attribute['count'])) {
            $value_count = absint($attribute['count']);
        }
        // Attribute term counts can include multiple values on one variable product.
        $estimated_covered_product_count = min($product_count, $assignment_count);
        $coverage = $product_count ? min(1, $assignment_count / $product_count) : 0;
        $distribution = $this->get_distribution($value_counts, $assignment_count, $value_count);
        $unique_value_ratio = $estimated_covered_product_count
            ? min(1, $value_count / $estimated_covered_product_count)
            : 0;
        $semantic = $this->get_semantic_priority($taxonomy, isset($attribute['label']) ? $attribute['label'] : '');
        $excluded_reasons = array();
        if ($coverage < $thresholds['minimum_coverage']) {
            $excluded_reasons[] = 'low_coverage';
        }
        if ($value_count < $thresholds['minimum_value_count']) {
            $excluded_reasons[] = 'too_few_values';
        }
        if ($value_count > $thresholds['maximum_value_count']) {
            $excluded_reasons[] = 'too_many_values';
        }
        if ($unique_value_ratio >= $thresholds['nearly_unique_value_ratio']) {
            $excluded_reasons[] = 'nearly_unique_values';
        }
        if ($distribution['largest_value_share'] >= $thresholds['maximum_dominant_value_share']) {
            $excluded_reasons[] = 'single_value_dominates';
        }

        return array(
            'taxonomy' => $taxonomy,
            'label' => isset($attribute['label']) ? $attribute['label'] : $taxonomy,
            'product_count' => $product_count,
            'assignment_count' => $assignment_count,
            'estimated_covered_product_count' => $estimated_covered_product_count,
            'coverage' => round($coverage, 4),
            'value_count' => $value_count,
            'distribution' => $distribution,
            'uniqueness' => array(
                'unique_value_ratio' => round($unique_value_ratio, 4),
                'average_products_per_value' => $value_count ? round($assignment_count / $value_count, 2) : 0.0,
                'is_nearly_unique' => $unique_value_ratio >= $thresholds['nearly_unique_value_ratio'],
            ),
            'semantic' => $semantic,
            'eligible' => empty($excluded_reasons),
            'excluded_reasons' => $excluded_reasons,
        );
    }

    protected function get_distribution($value_counts, $assignment_count, $value_count) {
        if (!$assignment_count || !$value_count || empty($value_counts)) {
            return array(
                'largest_value_share' => 0.0,
                'normalized_entropy' => 0.0,
                'is_evenly_distributed' => false,
            );
        }
        $largest_value_share = max($value_counts) / $assignment_count;
        $entropy = 0.0;
        foreach ($value_counts as $count) {
            $share = $count / $assignment_count;
            if ($share > 0) {
                $entropy -= $share * log($share);
            }
        }
        $normalized_entropy = $value_count > 1 ? $entropy / log($value_count) : 0.0;
        return array(
            'largest_value_share' => round($largest_value_share, 4),
            'normalized_entropy' => round($normalized_entropy, 4),
            'is_evenly_distributed' => $normalized_entropy >= 0.60,
        );
    }

    protected function get_semantic_priority($taxonomy, $label) {
        $name = strtolower(str_replace('pa_', '', (string)$taxonomy) . ' ' . (string)$label);
        $priorities = array(
            'brand' => array('priority' => 1.00, 'kind' => 'brand'),
            'colour' => array('priority' => 0.95, 'kind' => 'color'),
            'color' => array('priority' => 0.95, 'kind' => 'color'),
            'size' => array('priority' => 0.90, 'kind' => 'size'),
            'material' => array('priority' => 0.80, 'kind' => 'material'),
            'capacity' => array('priority' => 0.75, 'kind' => 'capacity'),
            'volume' => array('priority' => 0.75, 'kind' => 'capacity'),
            'gender' => array('priority' => 0.70, 'kind' => 'gender'),
            'style' => array('priority' => 0.65, 'kind' => 'style'),
        );
        $semantic = array('priority' => 0.40, 'kind' => 'generic');
        foreach ($priorities as $keyword => $priority) {
            if (strpos($name, $keyword) !== false) {
                $semantic = $priority;
                break;
            }
        }
        return apply_filters('brapf_one_click_attribute_semantic_priority', $semantic, $taxonomy, $label);
    }

    protected function get_product_count($catalog_snapshot) {
        if (!is_array($catalog_snapshot)
            || empty($catalog_snapshot['catalog'])
            || empty($catalog_snapshot['catalog']['products'])) {
            return 0;
        }
        return absint(isset($catalog_snapshot['catalog']['products']['count'])
            ? $catalog_snapshot['catalog']['products']['count']
            : 0);
    }
}
