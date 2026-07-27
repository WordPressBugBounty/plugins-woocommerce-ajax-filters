var braapf_init_jqrui_slidr,
braapf_jqrui_slidr_same,
braapf_jqrui_slidr_values_wc_price,
braapf_init_jqrui_slidr_for_parent,
braapf_grab_single_jqrui,
braapf_jqrui_slidr_values_arr_attr,
braapf_jqrui_slidr_values_link_arr_attr;
(function ($){
    function braapf_slider_input_focusin(input, position) {
        var $slider = $(input).closest('.bapf_slidr_jqrui.bapf_slidr_ready').find('.bapf_slidr_main');
        var values = $slider.slider('values');
        $(input).val(values[position]);
        $(input).data('val', values[position]);
    }
    function braapf_slider_input_focusout_change(input, position, trigger) {
        var $slider = $(input).closest('.bapf_slidr_jqrui.bapf_slidr_ready').find('.bapf_slidr_main');
        if( trigger == 'focusout' ) {
            if( $(input).val() == $(input).data('val') ) {
                var values = $slider.slider('values');
                $slider.trigger('braapf_change_jqrui_slidr', [values]);
            }
        } else {
            var val = parseInt($(input).val());
            $slider.slider('values', position, val);
        }
    }
    $.each([{position:0, className:'bapf_from'}, {position:1, className:'bapf_to'}], function(i, val) {
        $(document).on('focusin', '.bapf_slidr_jqrui.bapf_slidr_ready .'+val.className+' input[type=text]', function() {
            braapf_slider_input_focusin(this, val.position);
        });
        $(document).on('change focusout', '.bapf_slidr_jqrui.bapf_slidr_ready .'+val.className+' input[type=text]', function(event) {
            braapf_slider_input_focusout_change(this, val.position, event.type);
        });
        $(document).on('change', '.bapf_slidr_jqrui.bapf_slidr_ready .'+val.className+' select', function(event) {
            braapf_slider_input_focusout_change(this, val.position, event.type);
        });
    });
    //SPAN CHANGED TEXT
    $(document).on('braapf_change_jqrui_slidr', '.bapf_slidr_jqrui .bapf_slidr_main', function(event, values) {
        var $element = $(this);
        var input_values = [values[0], values[1]];
        input_values = berocket_apply_filters('jqrui_slidr_'+$element.data('display'), input_values, $element);
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_from span.bapf_val').length ) {
            $element.closest('.bapf_slidr_jqrui').find('.bapf_from span.bapf_val').text(input_values[0]);
        }
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_to span.bapf_val').length ) {
            $element.closest('.bapf_slidr_jqrui').find('.bapf_to span.bapf_val').text(input_values[1]);
        }
    });
    //INPUT CHANGED TEXT
    $(document).on('braapf_change_jqrui_slidr', '.bapf_slidr_jqrui .bapf_slidr_main', function(event, values) {
        var $element = $(this);
        var input_values = [values[0], values[1]];
        input_values = berocket_apply_filters('jqrui_slidr_'+$element.data('display'), input_values, $element);
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_from input[type=text]').length ) {
            $element.closest('.bapf_slidr_jqrui').find('.bapf_from input[type=text]').val(input_values[0]);
        }
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_to input[type=text]').length ) {
            $element.closest('.bapf_slidr_jqrui').find('.bapf_to input[type=text]').val(input_values[1]);
        }
    });
    //SELECT CHANGED
    $(document).on('braapf_change_jqrui_slidr', '.bapf_slidr_jqrui .bapf_slidr_main', function(event, values) {
        var $element        = $(this);
        var attr            = $element.data('attr');
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_from select').length || $element.closest('.bapf_slidr_jqrui').find('.bapf_to select').length ) {
            var attr = $element.data('attr');
            var from_options    = [];
            var to_options      = [];
            var from_end = false, to_start = false;
            $.each(attr, function(i, val) {
                if( i == values[0] ) to_start = true;
                if( ! from_end ) {
                    from_options.push({v:val.v, n:val.n, ov:i});
                }
                if( to_start ) {
                    to_options.push({v:val.v, n:val.n, ov:i});
                }
                if( i == values[1] ) from_end = true;
            });
        }
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_from select').length ) {
            $element.closest('.bapf_slidr_jqrui').find('.bapf_from select option').remove();
            $.each(from_options, function(i, val) {
                var selected = '';
                if( val.ov == values[0] ) {
                    selected = ' selected';
                }
                $element.closest('.bapf_slidr_jqrui').find('.bapf_from select').append($('<option value="'+val.ov+'"'+selected+'>'+val.n+'</option>'));
            });
        }
        if( $element.closest('.bapf_slidr_jqrui').find('.bapf_to select').length ) {
            $element.closest('.bapf_slidr_jqrui').find('.bapf_to select option').remove();
            $.each(to_options, function(i, val) {
                var selected = '';
                if( val.ov == values[1] ) {
                    selected = ' selected';
                }
                $element.closest('.bapf_slidr_jqrui').find('.bapf_to select').append($('<option value="'+val.ov+'"'+selected+'>'+val.n+'</option>'));
            });
        }
    });
    braapf_init_jqrui_slidr = function() {
        braapf_init_jqrui_slidr_for_parent($(document));
    }
    braapf_init_jqrui_slidr_for_parent = function($parent) {
        $parent.find( ".bapf_slidr_jqrui:not(.bapf_slidr_ready)" ).each(function() {
            var $slider = $(this).find('.bapf_slidr_main');
            var slider_data = berocket_apply_filters('jqrui_data_slidr_jqrui', {
                range: true,
                min: $slider.data('min'),
                max: $slider.data('max'),
                values: [ $slider.data('start'), $slider.data('end') ],
                step: $slider.data('step'),
                create:function(event, ui) {
                    var values = $(this).slider('values');
                    $(this).trigger('braapf_change_jqrui_slidr', [values]);
                },
                slide:function(event, ui) {
                    $(this).trigger('braapf_change_jqrui_slidr', [ui.values]);
                },
                change:function(event, ui) {
                    var values = $(this).slider('values');
                    $(this).trigger('braapf_change_jqrui_slidr', [values]);
                    if( !$(this).is('.bapf_jqrui_blocked') ) {
                        var values = $(this).slider('values');
                        var taxonomy = $(this).parents('.bapf_sfilter').data('taxonomy');
                        braapf_jqrui_slidr_same(taxonomy, values);
                        var filter_changed_element = {
                            element:'#'+$(this).closest('.bapf_sfilter').attr('id'),
                            parent: 0,
                            find: '.bapf_body'
                        };
                        berocket_apply_filters('filter_changed_element', filter_changed_element, $(this));
                        berocket_do_action('update_products', 'filter', $(this));
                    }
                },
            }, $slider);
            $slider.slider(slider_data);
            $(this).addClass('bapf_slidr_ready');
        });
    }
    braapf_jqrui_slidr_same = function (taxonomy, values) {
        $('.bapf_slidr_jqrui.bapf_slidr_ready[data-taxonomy="'+taxonomy+'"]').each(function() {
            var $slider = $(this).find('.bapf_slidr_main');
            $slider.addClass('bapf_jqrui_blocked');
            $slider.slider('values', values);
            $slider.removeClass('bapf_jqrui_blocked');
        });
    }
    braapf_jqrui_slidr_values_num_attr_style = function(values, $element) {
        var number_style = $element.data('number_style');
        if( number_style ) {
            values[0] = berocket_format_number (values[0], number_style );
            values[1] = berocket_format_number (values[1], number_style );
        }
        return values;
    }
    braapf_jqrui_slidr_values_wc_price = function(values, $element) {
        var number_style = $element.data('number_style');
        if( ! number_style ) {
            number_style = the_ajax_script.number_style;
        }
        values[0] = berocket_format_number (values[0], number_style );
        values[1] = berocket_format_number (values[1], number_style );
        return values;
    }
    braapf_grab_single_jqrui = function(single_data, element) {
        if( element.is('.bapf_slidr_jqrui.bapf_slidr_ready') && single_data != false ) {
            var $slider = element.find('.bapf_slidr_main');
            var values = $slider.slider('values');
            var input_values = $slider.slider('values');
            var prefix = '';
            if( element.find('.bapf_tbprice').length ) {
                prefix = element.find('.bapf_tbprice').first().text();
            }
            var postfix = '';
            if( element.find('.bapf_taprice').length ) {
                postfix = element.find('.bapf_taprice').first().text();
            }
            if( values[0] != $slider.data('min') || values[1] != $slider.data('max') ) {
                input_values = berocket_apply_filters('jqrui_slidr_'+$slider.data('display'), input_values, $slider);
                input_values[0] = prefix + input_values[0] + postfix;
                input_values[1] = prefix + input_values[1] + postfix;
                var value_ready = {value:values[0]+'_'+values[1], html:input_values[0]+' - '+input_values[1]};
                value_ready = berocket_apply_filters('jqrui_slidr_link_'+$slider.data('display'), value_ready, values, input_values, $slider, single_data);
                single_data.values = [value_ready];
            }
        }
        return single_data;
    }
    braapf_jqrui_slidr_values_arr_attr = function(values, $element) {
        var attr = $element.data('attr');
        if( Array.isArray(values) && values.length == 2 ) {
            values[0] = attr[values[0]].n;
            values[1] = attr[values[1]].n;
        } else {
            values = ['', ''];
            values[0] = attr[0].n;
            values[1] = attr[attr.length - 1].n;
        }
        return values;
    }
    braapf_jqrui_slidr_values_link_arr_attr = function(value_ready, values, input_values, $slider, single_data) {
        var attr = $slider.data('attr');
        value_ready.value = attr[values[0]].v+'_'+attr[values[1]].v;
        return value_ready;
    }
    $(document).on('braapf_unselect braapf_unselect_all', '.bapf_slidr_jqrui', function(event, data) {
        var $slider = $(this).find('.bapf_slidr_main');
        var min = $slider.data('min');
        var max = $slider.data('max');
        $slider.addClass('bapf_jqrui_blocked');
        $slider.slider('values', [min, max]);
        $slider.removeClass('bapf_jqrui_blocked');
    });
    function braapf_jqrui_slidr_berocket_add_filter() {
        berocket_add_filter('jqrui_slidr_wc_price', braapf_jqrui_slidr_values_wc_price);
        berocket_add_filter('jqrui_slidr_num_attr', braapf_jqrui_slidr_values_num_attr_style);
        berocket_add_filter('jqrui_slidr_arr_attr', braapf_jqrui_slidr_values_arr_attr);
        berocket_add_filter('jqrui_slidr_arr_attr_price', braapf_jqrui_slidr_values_arr_attr, 10);
        berocket_add_filter('jqrui_slidr_arr_attr_price', braapf_jqrui_slidr_values_wc_price, 20);
        berocket_add_filter('jqrui_slidr_link_arr_attr', braapf_jqrui_slidr_values_link_arr_attr);
        berocket_add_filter('jqrui_slidr_link_arr_attr_price', braapf_jqrui_slidr_values_link_arr_attr);
        berocket_add_filter('grab_single_filter_default', braapf_grab_single_jqrui);
        berocket_add_filter('braapf_init', braapf_init_jqrui_slidr);
        berocket_add_filter('braapf_init_for_parent', braapf_init_jqrui_slidr_for_parent);
    }
    if ( typeof(berocket_add_filter) == 'function' ) {
        braapf_jqrui_slidr_berocket_add_filter();
    } else {
        jQuery(document).on('berocket_hooks_ready', function() {
            braapf_jqrui_slidr_berocket_add_filter();
        });
    }
})(jQuery);
