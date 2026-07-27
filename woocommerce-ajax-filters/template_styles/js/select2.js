var bapf_select2_init,
bapf_select2_init_for_parent,
bapf_select2_disable_for_parent;
jQuery(document).ready(function() {
    bapf_select2_init = function() {
        bapf_select2_init_for_parent(jQuery(document));
    }
    bapf_select2_init_for_parent = function($parent) {
        if( $parent.find('.bapf_select2').length && typeof($parent.find('.bapf_select2').select2) != 'undefined' ) {
            $parent.find('.bapf_select2').each(function() {
                if ( ! jQuery(this).data('select2') && ! jQuery(this).is('.select2-hidden-accessible') ) { 
                    var select2data = {width:'100%', theme:'default'};
                    if (jQuery(this).prop('multiple') ) {
                        select2data.placeholder = jQuery(this).data('placeholder');
                    }
                    if( jQuery(this).parents('#berocket-ajax-filters-sidebar').length ) {
                        if( jQuery('#bapf-select2-high-zindex').length == 0 ) {
                            jQuery('body').append('<div id="bapf-select2-high-zindex"></div>');
                        }
                        select2data.dropdownParent = jQuery('#bapf-select2-high-zindex');
                    }
                    select2data = berocket_apply_filters('jqrui_data_select2', select2data, jQuery(this));
                    jQuery(this).select2(select2data);
                }
            });
        }
    }
    bapf_select2_disable_for_parent = function($parent) {
        if( $parent.find('.bapf_select2').length && typeof($parent.find('.bapf_select2').select2) != 'undefined' ) {
            $parent.find('.bapf_select2').each(function() {
                if ( jQuery(this).data('select2') ) {
                    jQuery(this).select2('destroy');
                }
            });
        }
    }
    jQuery(document).on('berocket_ajax_filtering_on_update', function() {
        if( jQuery('.bapf_sfilter .bapf_select2').length && typeof(jQuery('.bapf_sfilter .bapf_select2').select2) == 'function' ) {
            jQuery('.bapf_sfilter .bapf_select2').each(function() {
                if (jQuery(this).data('select2')) {
                    jQuery(this).select2('close');
                }
            });
        }
        bapf_select2_disable_for_parent(jQuery(document));
    });
    function bapf_select2_berocket_add_filter() {
        bapf_select2_init();
        berocket_add_filter('braapf_init', bapf_select2_init, 2000);
        berocket_add_filter('braapf_init_for_parent', bapf_select2_init_for_parent);
    }
    if ( typeof(berocket_add_filter) == 'function' ) {
        bapf_select2_berocket_add_filter();
    } else {
        jQuery(document).on('berocket_hooks_ready', function() {
            bapf_select2_berocket_add_filter();
        });
    }
});