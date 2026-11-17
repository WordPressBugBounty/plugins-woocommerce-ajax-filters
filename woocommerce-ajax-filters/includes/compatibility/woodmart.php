<?php
if( ! class_exists('BeRocket_AAPF_compat_woodmart') ) {
    class BeRocket_AAPF_compat_woodmart {
        function __construct() {
            add_action('braapf_wp_enqueue_script_after', array($this, 'enqueue_script'));
        }
        function enqueue_script($handle) {
            if( $handle !== 'berocket_aapf_widget-script' || get_template() !== 'woodmart' ) {
                return;
            }
            wp_enqueue_script(
                'berocket_aapf_woodmart',
                plugins_url('assets/themes/woodmart.js', BeRocket_AJAX_filters_file),
                array('jquery', 'berocket_aapf_widget-script'),
                BeRocket_AJAX_filters_version,
                true
            );
        }
    }
    new BeRocket_AAPF_compat_woodmart();
}
