<?php
if( ! class_exists('BeRocket_AAPF_compat_multivendorx') ) {
    class BeRocket_AAPF_compat_multivendorx {
        public function __construct() {
            add_filter( 'multivendorx_store_product_query_args', array( $this, 'store_product_query_args' ), 100, 4 );
            add_filter( 'multivendorx_rewrite_rules', array( $this, 'store_rewrite_rules' ), 100, 2 );
            add_filter( 'multivendorx_query_vars', array( $this, 'store_query_vars' ), 100, 2 );
            add_filter( 'aapf_localize_widget_script', array( $this, 'localize_widget_script' ), 100, 1 );
        }

        public function store_product_query_args( $args, $store_id, $search_keyword, $paged_number ) {
            if( empty($store_id) || ! is_array($args) || ! $this->is_store_page($store_id) ) {
                return $args;
            }

            $args['bapf_apply'] = true;
            $args['bapf_save_query'] = true;

            return $args;
        }

        public function store_rewrite_rules( $rules, $rewrite ) {
            $permalink = $this->get_permalink_options();
            if( empty($permalink['variable']) || ! $this->is_nice_urls_enabled() ) {
                return $rules;
            }

            $store_base = $this->get_store_base($rewrite);
            $filter_var = preg_quote($permalink['variable'], '#');

            $new_rules = array(
                array(
                    '^' . $store_base . '/([^/]+)/' . $filter_var . '/(.+?)/page/([0-9]{1,})/?$',
                    'index.php?' . $store_base . '=$matches[1]&' . $permalink['variable'] . '=$matches[2]&paged=$matches[3]',
                    'top',
                ),
                array(
                    '^' . $store_base . '/([^/]+)/' . $filter_var . '/(.+?)/?$',
                    'index.php?' . $store_base . '=$matches[1]&' . $permalink['variable'] . '=$matches[2]',
                    'top',
                ),
            );

            return array_merge($new_rules, $rules);
        }

        public function store_query_vars( $vars, $rewrite ) {
            $permalink = $this->get_permalink_options();
            if( ! empty($permalink['variable']) && ! in_array($permalink['variable'], $vars, true) ) {
                $vars[] = $permalink['variable'];
            }
            return $vars;
        }

        public function localize_widget_script( $localize ) {
            $localize['products_holder_id'] = $this->append_selector(
                br_get_value_from_array($localize, 'products_holder_id'),
                '.multivendorx-store-wrapper ul.products'
            );

            return $localize;
        }

        protected function append_selector( $selector, $add_selector ) {
            if( empty($selector) ) {
                return $add_selector;
            }
            if( strpos($selector, $add_selector) !== FALSE ) {
                return $selector;
            }
            return $selector . ', ' . $add_selector;
        }

        protected function is_store_page( $store_id = 0 ) {
            $store_slug = get_query_var($this->get_store_base());
            if( empty($store_slug) ) {
                return false;
            }

            if( empty($store_id) ) {
                return true;
            }

            $store = $this->get_current_store($store_slug);
            return ( is_object($store) && method_exists($store, 'get_id') && intval($store->get_id()) === intval($store_id) );
        }

        protected function get_current_store( $store_slug = '' ) {
            if( empty($store_slug) ) {
                $store_slug = get_query_var($this->get_store_base());
            }

            if( empty($store_slug) || ! class_exists('\MultiVendorX\Store\Store') ) {
                return false;
            }

            return \MultiVendorX\Store\Store::get_store($store_slug, 'slug');
        }

        protected function get_store_base( $rewrite = false ) {
            if( is_object($rewrite) && ! empty($rewrite->custom_store_url) ) {
                return sanitize_title($rewrite->custom_store_url);
            }

            $store_base = 'store';
            if( function_exists('MultiVendorX') && is_object(MultiVendorX()) && ! empty(MultiVendorX()->setting) ) {
                $store_base = MultiVendorX()->setting->get_setting('store_url', 'store');
            }

            return sanitize_title($store_base);
        }

        protected function is_nice_urls_enabled() {
            if( ! class_exists('BeRocket_AAPF') ) {
                return false;
            }

            $BeRocket_AAPF = BeRocket_AAPF::getInstance();
            $options = $BeRocket_AAPF->get_option();
            return ! empty($options['nice_urls']);
        }

        protected function get_permalink_options() {
            $default = array(
                'variable' => 'filters',
                'value'    => '/values',
                'split'    => '/',
            );

            $option_permalink = get_option('berocket_permalink_option');
            if( ! is_array($option_permalink) ) {
                $option_permalink = array();
            }

            return apply_filters('bapf_nice_urls_get_permalinks_options', array_merge($default, $option_permalink));
        }
    }

    new BeRocket_AAPF_compat_multivendorx();
}
