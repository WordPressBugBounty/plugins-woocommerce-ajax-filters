var braapf_grab_single_select;
(function ($){
    $(document).on('change', '.bapf_slct .bapf_body select', function() {
        var filter_changed_element = {
            element:'#'+$(this).closest('.bapf_sfilter').attr('id'),
            parent: 0,
            find: '.bapf_body'
        };
        berocket_apply_filters('filter_changed_element', filter_changed_element, $(this));
        berocket_do_action('update_products', 'filter', $(this));
    });
    braapf_grab_single_select = function(single_data, element) {
        if( element.is('.bapf_slct') && single_data != false ) {
            var $elements = $('.bapf_slct[data-taxonomy="'+single_data.taxonomy+'"]');
            $elements = braapf_filter_mobile_desktop_filters($elements);
            var $select = $elements.find('.bapf_body select:not(:disabled)');
            var added_values = [];
            $select.find('option:selected:not(:disabled)').each(function() {
                var value = $(this).val();
                if( value && added_values.indexOf(value) === -1 ) {
                    added_values.push(value);
                    single_data.values.push({value: value, html: $(this).data('name')})
                }
            });
        }
        return single_data;
    }
    $(document).on('braapf_unselect braapf_unselect_all', '.bapf_slct', function(event, data) {
        $(this).find('.bapf_body select:not(:disabled) option:selected:not(:disabled)').each(function() {
            if( typeof(data) == 'undefined' || typeof(data.value) == 'undefined' || data.value == $(this).val() ) {
                $(this).prop('selected', false);
            }
        });
    });
    if ( typeof(berocket_add_filter) == 'function' ) {
        berocket_add_filter('grab_single_filter_default', braapf_grab_single_select);
    } else {
        jQuery(document).on('berocket_hooks_ready', function() {
            berocket_add_filter('grab_single_filter_default', braapf_grab_single_select);
        });
    }
})(jQuery);
