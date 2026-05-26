<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function bapf_divi5_get_modules( $module_name = '' ) {
    $first_filter_id = bapf_divi5_get_first_post_id( 'br_product_filter' );

    $modules = array(
        'bapf/single-filter' => array(
            'name'         => 'bapf/single-filter',
            'module_dir'   => 'single-filter',
            'module_class' => 'et_pb_br_filter_single',
            'type'         => 'single',
            'defaults'     => array(
                'filter_id'   => $first_filter_id,
                'title_level' => '',
            ),
        ),
        'bapf/filters-group' => array(
            'name'         => 'bapf/filters-group',
            'module_dir'   => 'filters-group',
            'module_class' => 'et_pb_br_filters_group',
            'type'         => 'group',
            'defaults'     => array(
                'group_id'                   => '0',
                'display_inline'             => 'off',
                'display_inline_count'       => '',
                'min_filter_width_inline'    => '25',
                'hidden_clickable'           => 'off',
                'hidden_clickable_hover'     => 'off',
                'group_is_hide'              => 'off',
                'group_is_hide_theme'        => '0',
                'group_is_hide_icon_theme'   => '0',
                'title_level'                => '',
            ),
        ),
        'bapf/filter-next' => array(
            'name'         => 'bapf/filter-next',
            'module_dir'   => 'filter-next',
            'module_class' => 'et_pb_braapf_filter_next',
            'type'         => 'filter_next',
            'defaults'     => array(),
        ),
        'bapf/filters-group-item' => array(
            'name'         => 'bapf/filters-group-item',
            'module_dir'   => 'filters-group-item',
            'module_class' => 'et_pb_br_filters_group_item',
            'type'         => 'group_item',
            'defaults'     => array(
                'filter_id' => $first_filter_id,
            ),
        ),
        'bapf/sidebar-button' => array(
            'name'         => 'bapf/sidebar-button',
            'module_dir'   => 'sidebar-button',
            'module_class' => 'et_pb_br_sidebar_button',
            'type'         => 'sidebar_button',
            'defaults'     => array(
                'theme'      => '0',
                'icon-theme' => '0',
                'title'      => 'SHOW FILTERS',
            ),
        ),
    );

    if ( '' !== $module_name ) {
        return isset( $modules[ $module_name ] ) ? $modules[ $module_name ] : array();
    }

    return $modules;
}

function bapf_divi5_get_first_post_id( $post_type ) {
    $query = new WP_Query(
        array(
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        )
    );
    $posts = $query->get_posts();

    return ( is_array( $posts ) && count( $posts ) ) ? strval( array_shift( $posts ) ) : '0';
}
