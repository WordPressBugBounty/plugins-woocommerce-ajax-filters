<?php
include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/wc-widgets.php");
if( defined('ET_CORE_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/divi-theme-builder.php");
}
if( defined('FL_BUILDER_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/beaver-builder.php");
}
if( defined( 'ELEMENTOR_PRO_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/elementor-pro.php");
}
if( class_exists('RankMath') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/rank_math_seo.php");
}
if( function_exists('wmc_get_price') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/woo-multi-currency.php");
}
if( defined('WOOCS_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/woocs.php");
}
if ( ((defined( 'WCML_VERSION' ) || defined('POLYLANG_VERSION')) && defined( 'ICL_LANGUAGE_CODE' )) || function_exists('wpm_get_language') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/wpml.php");
}
if( class_exists('WCPBC_Pricing_Zones') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/price-based-on-country.php");
}
if( defined( 'DE_DB_WOO_VERSION' ) ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/bodycommerce.php");
}
if( defined( 'WCJ_PLUGIN_FILE' ) ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/woojetpack.php");
}
if( function_exists('relevanssi_do_query') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/relevanssi.php");
}
if( function_exists('premmerce_multicurrency') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/premmerce-multicurrency.php");
}
if( ! empty($GLOBALS['woocommerce-aelia-currencyswitcher']) ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/aelia-currencyswitcher.php");
}
if( defined( 'SEARCHWP_WOOCOMMERCE_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/wpsearch_wc_compatibility.php");
}
if( defined( 'WPB_VC_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/js_composer.php");
}
if( defined( 'UXTHEMES_ACCOUNT_URL') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/flatsome-ux-builder.php");
}
if( defined( '__BREAKDANCE_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/oxygen_builder.php");
}
if( defined( 'SITEORIGIN_PANELS_VERSION') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/siteorigin.php");
}
if( defined( 'DS_LIVE_COMPOSER_VER') ) {
    include_once(plugin_dir_path( BeRocket_AJAX_filters_file ) . "includes/compatibility/live_composer.php");
}