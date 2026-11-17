<?php
if( ! class_exists('BeRocket_AAPF_Import_Export') ) {
    class BeRocket_AAPF_Import_Export {
        public static function init() {
            add_filter('brfr_import_export_type_config', array(__CLASS__, 'reference_type_config'), 10, 5);
            add_filter('berocket_conditions_import_export_schema', array(__CLASS__, 'condition_schema'), 10, 2);
            add_filter('berocket_aapf_single_filter_check_type_page', array(__CLASS__, 'legacy_page_condition_check'), 10, 3);
            add_filter('berocket_aapf_group_filters_check_type_page', array(__CLASS__, 'legacy_page_condition_check'), 10, 3);
            add_filter('berocket_aapf_seo_meta_post_check_type_page', array(__CLASS__, 'legacy_page_condition_check'), 10, 3);
        }

        public static function reference_type_config($type, $key, $options, $direction, $context_options) {
            if( ! is_array($type) || ! is_array($context_options) ) {
                return $type;
            }

            if( ! empty($type['aapf_filter_taxonomy_reference']) ) {
                if( ! array_key_exists('filter_type', $context_options) || ! function_exists('braapf_get_data_taxonomy_from_post') ) {
                    return $type;
                }
                $taxonomy = braapf_get_data_taxonomy_from_post($context_options);
                if( is_string($taxonomy) && $taxonomy !== '' && ($direction === 'import' || taxonomy_exists($taxonomy)) ) {
                    $type['taxonomy'] = $taxonomy;
                } else {
                    unset($type['export_type']);
                }
            } elseif( ! empty($type['aapf_parent_reference']) ) {
                if( $key === 'parent_product_cat_current' ) {
                    unset($type['export_type']);
                }
            }

            return $type;
        }

        public static function condition_schema($schema, $context = array()) {
            if( ! is_array($schema) ) {
                $schema = array();
            }
            $schema['attribute_filtering'] = array(
                'values' => array(
                    'export_type'     => 'taxonomy',
                    'taxonomy_by_key' => true,
                    'passthrough'     => array(''),
                    'on_missing'      => 'remove',
                ),
            );
            return $schema;
        }

        public static function migrate_page_conditions() {
            $post_types = array(
                'br_product_filter',
                'br_filters_group',
                'brapf_seo_meta',
            );
            $post_ids = get_posts(array(
                'post_type'        => $post_types,
                'post_status'      => array_keys(get_post_stati()),
                'posts_per_page'   => -1,
                'fields'           => 'ids',
                'orderby'          => 'ID',
                'order'            => 'ASC',
                'suppress_filters' => true,
                'no_found_rows'    => true,
            ));

            foreach($post_ids as $post_id) {
                $post_type = get_post_type($post_id);
                if( ! in_array($post_type, $post_types, true) ) {
                    continue;
                }
                $settings = get_post_meta($post_id, $post_type, true);
                if( ! is_array($settings) || ! isset($settings['data']) || ! is_array($settings['data']) ) {
                    continue;
                }
                $changed = false;
                $settings['data'] = self::migrate_page_condition_tree($settings['data'], $changed);
                if( $changed ) {
                    update_post_meta($post_id, $post_type, $settings);
                }
            }
        }

        public static function legacy_page_condition_check($show, $condition, $additional) {
            $changed = false;
            $condition = self::migrate_page_condition_tree($condition, $changed);
            return BeRocket_conditions::check_condition_page_id($show, $condition, $additional);
        }

        protected static function migrate_page_condition_tree($conditions, &$changed) {
            if( ! is_array($conditions) ) {
                return $conditions;
            }
            if( isset($conditions['type']) && $conditions['type'] === 'page' ) {
                $pages = array();
                if( isset($conditions['pages']) ) {
                    $pages = is_array($conditions['pages']) ? $conditions['pages'] : array($conditions['pages']);
                }
                if( isset($conditions['page']) ) {
                    $legacy_pages = is_array($conditions['page']) ? $conditions['page'] : array($conditions['page']);
                    $pages = array_merge($pages, $legacy_pages);
                }
                $conditions['pages'] = self::normalize_page_values($pages);
                $conditions['type'] = 'page_id';
                unset($conditions['page']);
                $changed = true;
            }
            foreach($conditions as $key => $value) {
                if( is_array($value) ) {
                    $conditions[$key] = self::migrate_page_condition_tree($value, $changed);
                }
            }
            return $conditions;
        }

        protected static function normalize_page_values($pages) {
            $legacy_pages = array(
                'product_cat'      => 'category',
                'product_taxonomy' => 'taxonomies',
                'product_tag'      => 'tags',
                'single_product'   => 'product',
            );
            $normalized = array();
            foreach($pages as $page) {
                if( ! is_scalar($page) || $page === '' ) {
                    continue;
                }
                $page = (string) $page;
                if( isset($legacy_pages[$page]) ) {
                    $page = $legacy_pages[$page];
                }
                if( ! in_array($page, $normalized, true) ) {
                    $normalized[] = $page;
                }
            }
            return $normalized;
        }
    }

    BeRocket_AAPF_Import_Export::init();
}
