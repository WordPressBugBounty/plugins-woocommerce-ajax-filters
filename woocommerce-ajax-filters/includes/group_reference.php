<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolve a public filter-group reference to its current post ID.
 *
 * New integrations should pass the immutable group post slug. Numeric IDs are
 * intentionally supported for backwards compatibility with existing content.
 *
 * @param mixed $reference Group slug or legacy post ID.
 * @return int
 */
function braapf_resolve_group_id( $reference ) {
    static $resolved = array();

    if ( is_object( $reference ) && isset( $reference->ID ) ) {
        $reference = $reference->ID;
    }

    $reference = is_scalar( $reference ) ? trim( (string) $reference ) : '';
    if ( '' === $reference || '0' === $reference ) {
        return 0;
    }

    if ( isset( $resolved[ $reference ] ) ) {
        return $resolved[ $reference ];
    }

    // Legacy numeric references always retain their original meaning.
    if ( ctype_digit( $reference ) ) {
        $post_id = absint( $reference );
        if ( 'br_filters_group' === get_post_type( $post_id ) ) {
            return $resolved[ $reference ] = $post_id;
        }
    }

    $post = get_page_by_path( sanitize_title( $reference ), OBJECT, 'br_filters_group' );
    $post_id = $post instanceof WP_Post ? absint( $post->ID ) : 0;

    return $resolved[ $reference ] = $post_id;
}

/**
 * Return the portable reference used by newly saved integrations.
 */
function braapf_get_group_slug( $post_id ) {
    return 'br_filters_group' === get_post_type( $post_id )
        ? (string) get_post_field( 'post_name', $post_id )
        : '';
}

/**
 * Published group references used by builder-side legacy migrations.
 */
function braapf_get_group_id_to_slug_map() {
    $map = array( '0' => '0' );
    $post_ids = get_posts(
        array(
            'post_type'              => 'br_filters_group',
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters'       => false,
        )
    );

    foreach ( $post_ids as $post_id ) {
        $slug = braapf_get_group_slug( $post_id );
        if ( '' !== $slug ) {
            $map[ (string) absint( $post_id ) ] = $slug;
        }
    }

    return $map;
}

/**
 * Normalize shortcode/widget arguments while retaining the legacy field.
 */
function braapf_normalize_group_instance( $instance ) {
    $instance = is_array( $instance ) ? $instance : array();
    $legacy_reference = isset( $instance['group_id'] ) ? $instance['group_id'] : '';
    $slug_reference   = isset( $instance['group_slug'] ) ? trim( (string) $instance['group_slug'] ) : '';
    $group_id         = '' !== $slug_reference && '0' !== $slug_reference
        ? braapf_resolve_group_id( $slug_reference )
        : 0;

    if ( ! $group_id && '' !== (string) $legacy_reference && '0' !== (string) $legacy_reference ) {
        $group_id = braapf_resolve_group_id( $legacy_reference );
    }

    $instance['group_id'] = $group_id;

    return $instance;
}

/**
 * Convert legacy Divi 4 group-module attributes when builder content is saved.
 * Raw br_filters_group shortcodes are deliberately outside this migration.
 */
function braapf_migrate_divi_group_module_content( $content ) {
    if ( ! is_string( $content ) || false === stripos( $content, '[et_pb_br_filters_group' ) ) {
        return $content;
    }

    return preg_replace_callback(
        '/\[et_pb_br_filters_group\b([^\]]*)\]/i',
        function( $match ) {
            $attributes = shortcode_parse_atts( $match[1] );
            if ( ! is_array( $attributes ) ) {
                return $match[0];
            }

            $slug = isset( $attributes['group_slug'] ) ? trim( (string) $attributes['group_slug'] ) : '';
            if ( '' !== $slug && '0' !== $slug ) {
                $slug = braapf_get_group_slug( braapf_resolve_group_id( $slug ) );
            }
            if ( '' === $slug && isset( $attributes['group_id'] ) ) {
                $legacy_id = trim( (string) $attributes['group_id'] );
                if ( '0' === $legacy_id ) {
                    $slug = '0';
                } else {
                    $slug = braapf_get_group_slug( braapf_resolve_group_id( $legacy_id ) );
                }
            }

            if ( '' === $slug ) {
                return $match[0];
            }

            $attribute_text = preg_replace(
                '/\s+group_(?:id|slug)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s\]]+)/i',
                '',
                $match[1]
            );

            return '[et_pb_br_filters_group' . rtrim( $attribute_text ) . ' group_slug="' . esc_attr( $slug ) . '"]';
        },
        $content
    );
}

function braapf_migrate_group_builder_references_on_save( $data ) {
    if ( isset( $data['post_content'] ) ) {
        $data['post_content'] = braapf_migrate_divi_group_module_content( $data['post_content'] );
    }

    return $data;
}
add_filter( 'wp_insert_post_data', 'braapf_migrate_group_builder_references_on_save', 20 );
