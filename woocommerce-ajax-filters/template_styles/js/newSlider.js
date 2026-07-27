var braapf_init_ion_slidr,
braapf_ion_slidr_same,
braapf_jqrui_slidr_ion_value_wc_price,
braapf_jqrui_slidr_ion_value_arr_attr,
braapf_init_ion_slidr_for_parent,
braapf_grab_single_ion,
braapf_jqrui_slidr_ion_values_link_arr_attr;
(function ($){
    braapf_init_ion_slidr = function () {
        braapf_init_ion_slidr_for_parent($(document));
    }
    braapf_init_ion_slidr_for_parent = function($parent) {
        $parent.find(".bapf_slidr_ion:not(.bapf_slidr_ready)").each(function() {
            var $this = $(this).find('.bapf_slidr_all .bapf_slidr_main');
            var update_function = function(data) {
                if( !$this.is('.bapf_ion_blocked') ) {
                    $this.addClass('bapf_ion_blocked');
                    var taxonomy = $this.closest('.bapf_sfilter').data('taxonomy');
                    braapf_ion_slidr_same(taxonomy, data);
                    var filter_changed_element = {
                        element:'#'+$this.closest('.bapf_sfilter').attr('id'),
                        parent: 0,
                        find: '.bapf_body'
                    };
                    berocket_apply_filters('filter_changed_element', filter_changed_element, $this);
                    berocket_do_action('update_products', 'filter', $this);
                    $this.removeClass('bapf_ion_blocked');
                }
            }
            var ionRangeData = berocket_apply_filters('jqrui_data_slidr_ion', {
                type: "double",
                from: $this.data('start'),
                to: $this.data('end'),
                grid: false,
                force_edges: true,
                onFinish: update_function,
                onUpdate: update_function,
                prettify: function(value) {
                    value = berocket_apply_filters('jqrui_slidr_ion_'+$this.data('display'), value, $this);
                    return value;
                }
            }, $this);
            $this.ionRangeSlider(ionRangeData);
            $(this).addClass('bapf_slidr_ready');
        });
    }
    braapf_ion_slidr_same = function (taxonomy, data) {
        $('.bapf_slidr_ion.bapf_slidr_ready[data-taxonomy="'+taxonomy+'"]').each(function() {
            var $slider = $(this).find('.bapf_slidr_main');
            $slider.addClass('bapf_ion_blocked');
            var slider_data = $slider.data("ionRangeSlider");
            slider_data.update({from:data.from, to:data.to});
            $slider.removeClass('bapf_ion_blocked');
        });
    }
    braapf_jqrui_slidr_ion_value_wc_price = function (value, $element) {
        var number_style = $element.data('number_style');
        if( ! number_style ) {
            number_style = the_ajax_script.number_style;
        }
        value = berocket_format_number (parseFloat(value), number_style );
        return value;
    }
    braapf_jqrui_slidr_ion_value_arr_attr = function(value, $element) {
        var attr = $element.data('attr');
        value = attr[value].n;
        return value;
    }
    braapf_grab_single_ion = function(single_data, element) {
        if( element.is('.bapf_slidr_ion.bapf_slidr_ready') && single_data != false ) {
            var data = element.find(".bapf_slidr_main").data('ionRangeSlider');
            if( typeof(data) != 'undefined' ) {
                var $slider = element.find('.bapf_slidr_main');
                var values = [data.options.from, data.options.to];
                var input_values = [berocket_apply_filters('jqrui_slidr_ion_'+$slider.data('display'), data.options.from, $slider), berocket_apply_filters('jqrui_slidr_ion_'+$slider.data('display'), data.options.to, $slider)];
                var prefix = $slider.data('prefix');
                if( typeof(prefix) == 'undefined' ) {
                    prefix = '';
                }
                var postfix = $slider.data('postfix');
                if( typeof(postfix) == 'undefined' ) {
                    postfix = '';
                }
                input_values[0] = prefix + input_values[0] + postfix;
                input_values[1] = prefix + input_values[1] + postfix;
                if( values[0] != $slider.data('min') || values[1] != $slider.data('max') ) {
                    var value_ready = {value:values[0]+'_'+values[1], html:input_values[0]+' - '+input_values[1]};
                    value_ready = berocket_apply_filters('jqrui_slidr_ion_link_'+$slider.data('display'), value_ready, values, input_values, $slider, single_data);
                    single_data.values = [value_ready];
                }
            }
        }
        return single_data;
    }
    braapf_jqrui_slidr_ion_values_link_arr_attr = function(value_ready, values, input_values, $slider, single_data) {
        var attr = $slider.data('attr');
        value_ready.value = attr[values[0]].v+'_'+attr[values[1]].v;
        return value_ready;
    }
    $(document).on('braapf_unselect braapf_unselect_all', '.bapf_slidr_ion', function(event, data) {
        var $slider = $(this).find('.bapf_slidr_main');
        var slider_data = $slider.data("ionRangeSlider");
        $slider.addClass('bapf_ion_blocked');
        slider_data.update({from:slider_data.options.min, to:slider_data.options.max});
        $slider.removeClass('bapf_ion_blocked');
    });
    function braapf_jqrui_slidr_ion_berocket_add_filter() {
        berocket_add_filter('braapf_init', braapf_init_ion_slidr);
        berocket_add_filter('braapf_init_for_parent', braapf_init_ion_slidr_for_parent);
        berocket_add_filter('grab_single_filter_default', braapf_grab_single_ion);
        berocket_add_filter('jqrui_slidr_ion_link_arr_attr', braapf_jqrui_slidr_ion_values_link_arr_attr);
        berocket_add_filter('jqrui_slidr_ion_link_arr_attr_price', braapf_jqrui_slidr_ion_values_link_arr_attr);
        berocket_add_filter('jqrui_slidr_ion_wc_price', braapf_jqrui_slidr_ion_value_wc_price);
        berocket_add_filter('jqrui_slidr_ion_num_attr', braapf_jqrui_slidr_ion_value_wc_price);
        berocket_add_filter('jqrui_slidr_ion_arr_attr', braapf_jqrui_slidr_ion_value_arr_attr);
        berocket_add_filter('jqrui_slidr_ion_arr_attr_price', braapf_jqrui_slidr_ion_value_arr_attr, 10);
        berocket_add_filter('jqrui_slidr_ion_arr_attr_price', braapf_jqrui_slidr_ion_value_wc_price, 20);
    }
    if ( typeof(berocket_add_filter) == 'function' ) {
        braapf_jqrui_slidr_ion_berocket_add_filter();
    } else {
        jQuery(document).on('berocket_hooks_ready', function() {
            braapf_jqrui_slidr_ion_berocket_add_filter();
        });
    }
})(jQuery);
