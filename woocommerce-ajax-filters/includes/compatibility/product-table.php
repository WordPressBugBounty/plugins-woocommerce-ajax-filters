<?php
class BeRocket_AAPF_compat_product_table {
    function __construct() {
        add_action( 'plugins_loaded', array( __CLASS__, 'plugins_loaded' ), 1 );
    }
    public static function init() {
        if( ! defined('DOING_AJAX') || ! DOING_AJAX
        || ! isset($_POST['action']) || ! is_string($_POST['action'])
        || wp_unslash($_POST['action']) !== 'wcpt_load_products'
        || ( ! self::check_old_version() && ! self::check_new_version() ) ) {
            return;
        }

        if( ! isset($_POST['table_id']) || ! is_string($_POST['table_id']) ) {
            return;
        }

        $table_id = wp_unslash($_POST['table_id']);
        if( $table_id === '' || strlen($table_id) > 172
        || ! preg_match('/\Awcpt_[A-Za-z0-9_-]+\z/D', $table_id) ) {
            return;
        }

        $table_transient = get_transient($table_id);
        if( ! is_array($table_transient) ) {
            return;
        }
        $has_count_key = array_key_exists('total_posts', $table_transient)
            || array_key_exists('total_filtered_posts', $table_transient);

        if( class_exists('BeRocket_AAPF') && is_callable(array('BeRocket_AAPF', 'getInstance')) ) {
            $BeRocket_AAPF = BeRocket_AAPF::getInstance();
            if( is_object($BeRocket_AAPF) && is_callable(array($BeRocket_AAPF, 'get_option')) ) {
                $options = $BeRocket_AAPF->get_option();
                $options = is_array($options) ? $options : array();
                $filter_name = apply_filters(
                    empty($options['nice_urls']) ? 'berocket_aapf_filter_variable_name_nn' : 'berocket_aapf_filter_variable_name',
                    'filters'
                );
                if( is_string($filter_name) && $filter_name !== ''
                && isset($_POST[$filter_name]) && is_string($_POST[$filter_name])
                && $_POST[$filter_name] !== '' && function_exists('bapf_set_filter_field_ajax') ) {
                    bapf_set_filter_field_ajax(wp_unslash($_POST[$filter_name]));
                }
            }
        }

        if( ! $has_count_key ) {
            return;
        }

        $changed = false;
        foreach( array('total_posts', 'total_filtered_posts') as $count_key ) {
            if( array_key_exists($count_key, $table_transient) ) {
                unset($table_transient[$count_key]);
                $changed = true;
            }
        }

        if( $changed ) {
            set_transient($table_id, $table_transient, DAY_IN_SECONDS);
        }
    }
    public static function check_old_version() {
        if( ! class_exists('WC_Product_Table_Plugin') ) {
            return false;
        }
        if( function_exists('Barn2\Plugin\WC_Product_Table\wc_product_table')
        || function_exists('Barn2\Plugin\WC_Product_Table\wpt') ) {
            return true;
        }
        return function_exists('wc_product_table')
            && defined('WC_Product_Table_Plugin::VERSION')
            && version_compare((string) constant('WC_Product_Table_Plugin::VERSION'), '2.1.3', '>');
    }
    public static function check_new_version() {
        $version_constant = 'Barn2\Plugin\WC_Product_Table\PLUGIN_VERSION';
        return function_exists('Barn2\Plugin\WC_Product_Table\wpt')
            && defined($version_constant)
            && version_compare((string) constant($version_constant), '2.1.3', '>');
    }
    public static function plugins_loaded() {
        if( self::check_old_version() || self::check_new_version() ) {
            add_action('wp_ajax_wcpt_load_products', array(__CLASS__, 'init'), 1);
            add_action('wp_ajax_nopriv_wcpt_load_products', array(__CLASS__, 'init'), 1);
            add_filter('aapf_localize_widget_script', array( __CLASS__, 'aapf_localize_widget_script' ));
            add_action( 'wc_product_table_get_table', array( __CLASS__, 'wc_product_table_get_table' ), 10, 1 );
            add_action( 'wc_product_table_after_get_table', array( __CLASS__, 'wc_product_table_get_table' ), 10, 1 );
            add_action( 'wp_footer', array( __CLASS__, 'set_scripts' ), 9000 );
            self::not_ajax_functions();
            $wcpt_shortcode_defaults = get_option('wcpt_shortcode_defaults');
            $wcpt_shortcode_defaults = is_array($wcpt_shortcode_defaults) ? $wcpt_shortcode_defaults : array();
            $wcpt_shortcode_defaults['berocket_ajax'] = '1';
            update_option('wcpt_shortcode_defaults', $wcpt_shortcode_defaults);
        }
    }
    public static function wc_product_table_get_table($table) {
        $table_args = $table->args->get_args();
        $table->query->get_total_products();
        if( ! empty($table_args['berocket_ajax'])
        && method_exists($table->data_table, 'add_above')
        && method_exists($table->data_table, 'add_below') ) {
            $table->data_table->add_above('<div class="berocket_product_table_compat">');
            $table->data_table->add_below('</div>');
        }
    }
    public static function not_ajax_functions() {
        add_filter( 'wc_product_table_query_args', array( __CLASS__, 'woocommerce_shortcode_products_query' ), 100, 2 );
    }
    public static function woocommerce_shortcode_products_query( $query_vars, $table ) {
        $table_args = $table->args->get_args();
        if( empty($table_args['berocket_ajax']) ) {
            return $query_vars;
        }
        $query_vars = apply_filters('bapf_uparse_apply_filters_to_query_vars_save', $query_vars);
        global $berocket_parse_page_obj;
        $berocket_parse_page_obj->save_shortcode_query_vars($query_vars);
        return $query_vars;
    }
    public static function aapf_localize_widget_script($localize) {
        $localize['products_holder_id'] .= ( empty($localize['products_holder_id']) ? '' : ', ' ) . '.berocket_product_table_compat';
        return $localize;
    }
    public static function set_scripts() {
        $html = '<script>function bapf_barn2_product_table_reinit() {
            try {
                if( typeof(jQuery(".berocket_product_table_compat .wc-product-table").productTable) == "function" && ! jQuery(".berocket_product_table_compat > .dataTables_wrapper").length ) {jQuery(".berocket_product_table_compat .wc-product-table").productTable();}
            } catch(err){}
        };jQuery(document).on("berocket_ajax_products_loaded", bapf_barn2_product_table_reinit);</script>';
        echo $html;
    }
}
