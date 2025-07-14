<?php
function bapf_settings_get_elements_position() {
    $elements_position = array(
        array('value' => 'woocommerce_archive_description', 'text' => __('WooCommerce Description(in header)', 'BeRocket_AJAX_domain')),
        array('value' => 'woocommerce_before_shop_loop', 'text' => __('WooCommerce Before Shop Loop', 'BeRocket_AJAX_domain')),
        array('value' => 'woocommerce_after_shop_loop', 'text' => __('WooCommerce After Shop Loop', 'BeRocket_AJAX_domain')),
    );
    $additional_elements_position = apply_filters('bapf_elements_position_hook_additional', array());
    if( ! is_array($additional_elements_position) ) {
        if( is_string($additional_elements_position) ) {
            $additional_elements_position = array($additional_elements_position);
        } else {
            $additional_elements_position = array();
        }
    }
    foreach($additional_elements_position as $additional_elements_position_element) {
        if( is_string($additional_elements_position_element) ) {
            $elements_position[] = array('value' => $additional_elements_position_element, 'text' => $additional_elements_position_element);
        }
    }
    return $elements_position;
}
//Theme seletor presets
function bapf_settings_get_selectors_preset() {
    $selectors_preset = array(
        'default' => array(
            'name'      => __('Default Selectors', 'BeRocket_AJAX_domain'),
            'options'   => array(
                'products_holder_id'                => 'ul.products',
                'woocommerce_result_count_class'    => '.woocommerce-result-count',
                'woocommerce_ordering_class'        => 'form.woocommerce-ordering',
                'woocommerce_pagination_class'      => '.woocommerce-pagination',
            ),
            'themes' => array()
        ),
        'betheme' => array(
            'name'      => __('Betheme', 'BeRocket_AJAX_domain'),
            'options'   => array(
                'products_holder_id'                => 'div.products_wrapper ',
                'woocommerce_result_count_class'    => '',
                'woocommerce_ordering_class'        => '',
                'woocommerce_pagination_class'      => '.pager_wrapper',
            ),
            'themes' => array()
        ),
        'Enfold' => array(
            'name'      => __('Enfold', 'BeRocket_AJAX_domain'),
            'options'   => array(
                'products_holder_id'                => 'div.products_wrapper ',
                'woocommerce_result_count_class'    => '',
                'woocommerce_ordering_class'        => '',
                'woocommerce_pagination_class'      => '.pager_wrapper',
            ),
            'themes' => array(
                'Enfold' => array(
                    'check' => false,
                    'name_replace' => __('Enfold', 'BeRocket_AJAX_domain')
                )
            )
        ),
    );
    return $selectors_preset;
}
function bapf_settings_get_selectors_preset_js($args = array('select' => '.berocket_selectors_preset', 'option_name_template' => 'br_filters_options[%optname%]')) {
    $selectors_preset = bapf_settings_get_selectors_preset();
    ?><script>
var bapf_settings_selectors_presets = <?php echo json_encode($selectors_preset); ?>;
var bapf_settings_selectors_presets_args = <?php echo json_encode($args); ?>;
function bapf_settings_selectors_preset_js() {
    var selected_option = jQuery(bapf_settings_selectors_presets_args.select).val();
    if( typeof(bapf_settings_selectors_presets[selected_option]) != 'undefined' ) {
        jQuery.each(bapf_settings_selectors_presets[selected_option].options, function(option_slug) {
            var option_selector = '[name="'+bapf_settings_selectors_presets_args.option_name_template+'"]';
            option_selector = option_selector.replace('\%optname\%', option_slug);
            jQuery(option_selector).val(this);
        });
    }
}
jQuery(document).on('change', bapf_settings_selectors_presets_args.select, bapf_settings_selectors_preset_js);
<?php
$options_to_update = array();
foreach($selectors_preset as $selectors_preset_option) {
    $options_to_update = array_merge($options_to_update, $selectors_preset_option['options']);
}
$options_to_update = array_keys($options_to_update);
foreach($options_to_update as $option_to_update) {
    $option_selector = '[name="'.$args['option_name_template'].'"]';
    $option_selector = str_replace('%optname%', $option_to_update, $option_selector);
?>jQuery(document).on('change', '<?php echo $option_selector; ?>', function() {
    jQuery(bapf_settings_selectors_presets_args.select).val('');
});<?php
}
?>
</script><?php
}