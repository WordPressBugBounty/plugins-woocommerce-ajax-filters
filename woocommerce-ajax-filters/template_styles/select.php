<?php
if( ! class_exists('BeRocket_AAPF_Template_Style_select') ) {
    class BeRocket_AAPF_Template_Style_select extends BeRocket_AAPF_Template_Style {
        function __construct() {
            $this->data = array(
                'slug'          => 'select',
                'template'      => 'select',
                'name'          => 'Select',
                'file'          => __FILE__,
                'style_file'    => 'css/select.css',
                'script_file'   => 'js/select.js',
                'image'         => plugin_dir_url( __FILE__ ) . 'images/select.png',
                'version'       => '1.1',
                'sort_pos'      => '1',
                'name_price'    => 'Price Ranges Select',
                'image_price'   => plugin_dir_url( BeRocket_AJAX_filters_file ) . 'paid/template_styles/images/select-price.png',
            );
            parent::__construct();
        }
    }
    new BeRocket_AAPF_Template_Style_select();
}
if( ! class_exists('BeRocket_AAPF_Template_Style_select2') ) {
    class BeRocket_AAPF_Template_Style_select2 extends BeRocket_AAPF_Template_Style_select {
        function __construct() {
            parent::__construct();
            $this->data['slug']  = 'select2';
            $this->data['name']  = 'Select2';
            $this->data['image'] = plugin_dir_url( __FILE__ ) . 'images/select2.png';
            $this->data['sort_pos'] = '900';
            $this->data['name_price'] = 'Price Ranges Select2';
            $this->data['image_price'] = plugin_dir_url( BeRocket_AJAX_filters_file ) . 'paid/template_styles/images/select2-price.png';
        }
        function enqueue_all() {
            do_action('bapf_select2_load');
            parent::enqueue_all();
            if( file_exists(dirname($this->data['file']).'/js/select2.js') ) {
                BeRocket_AAPF::wp_enqueue_script( 'BeRocket_AAPF_script-add-'.sanitize_title('/js/select2.js'), plugins_url( '/js/select2.js', $this->data['file'] ), array('jquery'), $this->data['version'], true );
            }
        }
        function template_full($template, $terms, $berocket_query_var_title) {
            $this->array_set($template, array('template', 'content', 'filter', 'content', 'list', 'attributes', 'class'));
            $template['template']['content']['filter']['content']['list']['attributes']['class']['select2'] = 'bapf_select2';
            if( ! $berocket_query_var_title['single_selection'] ) {
                $template['template']['content']['filter']['content']['list']['attributes']['data-placeholder'] = $berocket_query_var_title['select_first_element_text'];
                if (isset($berocket_query_var_title['select2_selection_display']) && in_array($berocket_query_var_title['select2_selection_display'], array('count', 'first'), true)) {
                    $attributes = &$template['template']['content']['filter']['content']['list']['attributes'];
                    $attributes['data-selection-display'] = $berocket_query_var_title['select2_selection_display'];
                    /* Translate both forms here; the actual selection count changes in the browser. */
                    $attributes['data-selection-label-one'] = __('%d selected', 'BeRocket_AJAX_domain');
                    $attributes['data-selection-label-many'] = __('%d selected values', 'BeRocket_AJAX_domain');
                }
            }
            return $template;
        }
    }
    new BeRocket_AAPF_Template_Style_select2();
}
if( ! class_exists('BeRocket_AAPF_Template_Style_select2_classic') ) {
    class BeRocket_AAPF_Template_Style_select2_classic extends BeRocket_AAPF_Template_Style_select2 {
        function __construct() {
            parent::__construct();
            $this->data['slug']  = 'select2classic';
            $this->data['name']  = 'Select2 Classic';
            $this->data['image'] = plugin_dir_url( __FILE__ ) . 'images/select2classic.png';
            $this->data['name_price'] = 'Price Ranges Select2 Classic';
            $this->data['image_price'] = plugin_dir_url( BeRocket_AJAX_filters_file ) . 'paid/template_styles/images/select2classic-price.png';
        }
        function template_full($template, $terms, $berocket_query_var_title) {
            $template = parent::template_full($template, $terms, $berocket_query_var_title);
            $template['template']['content']['filter']['content']['list']['attributes']['data-theme'] = 'classic';
            return $template;
        }
    }
    new BeRocket_AAPF_Template_Style_select2_classic();
}
