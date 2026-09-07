<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gutenberg integration for the existing BeRocket filter shortcodes.
 *
 * The blocks intentionally store only a selected custom-post ID.  Rendering
 * remains delegated to the established shortcode/widget path so every filter
 * type, template style, condition, and third-party compatibility continues to
 * behave the same way as it does outside the block editor.
 */
class BeRocket_AAPF_Gutenberg {
    const EDITOR_SCRIPT_HANDLE          = 'berocket-aapf-gutenberg-editor';
    const EDITOR_STYLE_HANDLE           = 'berocket-aapf-gutenberg-editor-style';
    const PREVIEW_STYLE_HANDLE          = 'berocket-aapf-gutenberg-preview-style';
    const PREVIEW_RUNTIME_SCRIPT_HANDLE = 'berocket-aapf-gutenberg-preview-runtime';
    const PREVIEW_ION_SCRIPT_HANDLE     = 'berocket-aapf-gutenberg-ion-range-slider';
    const PREVIEW_TEMPLATE_STYLE_PREFIX = 'berocket-aapf-gutenberg-template-';
    const SELECTOR_CACHE_PREFIX         = 'bapf_gutenberg_selector_options_';
    const SELECTOR_CACHE_VERSION_PREFIX = 'bapf_gutenberg_selector_options_version_';

    protected $editor_assets_registered = false;
    protected $editor_data_localized    = false;
    protected $is_block_renderer_preview_request = false;
    /*
     * The iframed editor temporarily swaps the global WP_Styles instance.
     * Keep bookkeeping per registry so assets registered while collecting the
     * iframe are also registered in the outer editor registry when needed.
     */
    protected $preview_template_styles_registered = array();
    protected $preview_custom_css_added           = array();

    public function __construct() {
        add_action( 'init', array( $this, 'register_blocks' ), 20 );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_preview_assets' ) );
        add_action( 'save_post_br_product_filter', array( $this, 'invalidate_post_options_cache' ), 10, 2 );
        add_action( 'save_post_br_filters_group', array( $this, 'invalidate_post_options_cache' ), 10, 2 );
        add_action( 'before_delete_post', array( $this, 'invalidate_post_options_cache' ), 10, 2 );
        add_filter( 'allowed_block_types_all', array( $this, 'filter_allowed_block_types' ), 9999, 2 );
        add_filter( 'rest_request_before_callbacks', array( $this, 'restrict_block_renderer_preview' ), 10, 3 );
        add_filter( 'rest_request_after_callbacks', array( $this, 'reset_block_renderer_preview_request' ), 10, 3 );
    }

    /**
     * Registers shared editor assets and the two dynamic blocks.
     */
    public function register_blocks() {
        if ( ! $this->can_register_blocks() ) {
            return;
        }

        $this->register_editor_assets();

        $blocks_path = __DIR__ . '/gutenberg';
        register_block_type(
            $blocks_path . '/single-filter',
            array(
                'render_callback' => array( $this, 'render_single_filter' ),
            )
        );
        register_block_type(
            $blocks_path . '/filter-group',
            array(
                'render_callback' => array( $this, 'render_filter_group' ),
            )
        );
    }

    /**
     * Keep the dynamic block types registered for frontend rendering, while
     * removing them from the editor inserter for users who cannot manage the
     * WooCommerce catalog.  A stored block must still render for visitors,
     * regardless of the visitor's role.
     *
     * @param bool|string[]            $allowed_block_types Allowed block names.
     * @param WP_Block_Editor_Context $editor_context Editor context.
     * @return bool|string[]
     */
    public function filter_allowed_block_types( $allowed_block_types, $editor_context ) {
        if ( $this->can_use_gutenberg_blocks() ) {
            return $allowed_block_types;
        }

        if ( true === $allowed_block_types ) {
            $allowed_block_types = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
        }

        if ( ! is_array( $allowed_block_types ) ) {
            return $allowed_block_types;
        }

        return array_values( array_diff( $allowed_block_types, $this->get_block_names() ) );
    }

    /**
     * The core block renderer only checks whether the current user may edit
     * the page.  Limit BeRocket previews to WooCommerce managers too, so an
     * ordinary author cannot call the renderer endpoint directly.
     *
     * @param mixed           $response Existing REST response.
     * @param array           $handler Route handler.
     * @param WP_REST_Request $request REST request.
     * @return mixed
     */
    public function restrict_block_renderer_preview( $response, $handler, $request ) {
        if ( null !== $response || ! $this->is_block_renderer_request( $request ) ) {
            return $response;
        }

        if ( ! $this->can_use_gutenberg_blocks() ) {
            return new WP_Error(
                'bapf_gutenberg_preview_forbidden',
                __( 'Sorry, you are not allowed to preview BeRocket filter blocks.', 'BeRocket_AJAX_domain' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        $this->is_block_renderer_preview_request = true;

        return $response;
    }

    /**
     * The preview marker is request-scoped, so block rendering through an
     * ordinary REST post/page endpoint continues to return its frontend HTML.
     *
     * @param mixed           $response REST response.
     * @param array           $handler Route handler.
     * @param WP_REST_Request $request REST request.
     * @return mixed
     */
    public function reset_block_renderer_preview_request( $response, $handler, $request ) {
        if ( $this->is_block_renderer_request( $request ) ) {
            $this->is_block_renderer_preview_request = false;
        }

        return $response;
    }

    /**
     * Render the selected single-filter shortcode in a block wrapper.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function render_single_filter( $attributes ) {
        return $this->render_selected_post( $attributes, 'filterId', 'br_product_filter', 'br_filter_single', 'filter_id' );
    }

    /**
     * Render the selected filter-group shortcode in a block wrapper.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public function render_filter_group( $attributes ) {
        return $this->render_selected_post( $attributes, 'groupId', 'br_filters_group', 'br_filters_group', 'group_id' );
    }

    /**
     * Loads enough of the legacy frontend renderer for the block-renderer REST
     * endpoint. Normal page requests already load it through the existing wp
     * hook, whereas the REST endpoint does not reach that hook.
     */
    protected function ensure_filter_renderer_loaded() {
        if ( class_exists( 'BeRocket_AAPF_Widget' ) ) {
            return;
        }

        if ( class_exists( 'BeRocket_AAPF' ) ) {
            BeRocket_AAPF::getInstance()->plugins_loaded();
        }
    }

    /**
     * Registers editor-only assets. Both blocks use the same script so their
     * configuration and preview behavior stay identical.
     */
    protected function register_editor_assets() {
        if ( $this->editor_assets_registered ) {
            return;
        }

        $plugin_url          = plugin_dir_url( BeRocket_AJAX_filters_file );
        $editor_js_path      = __DIR__ . '/gutenberg/editor.js';
        $editor_css_path     = __DIR__ . '/gutenberg/editor.css';
        $preview_js_path     = __DIR__ . '/gutenberg/preview.js';
        $ion_js_path         = dirname( __DIR__ ) . '/template_styles/js/ion.rangeSlider.min.js';

        wp_register_style(
            self::EDITOR_STYLE_HANDLE,
            $plugin_url . 'includes/gutenberg/editor.css',
            array(),
            file_exists( $editor_css_path ) ? filemtime( $editor_css_path ) : BeRocket_AJAX_filters_version
        );
        wp_register_style(
            self::PREVIEW_STYLE_HANDLE,
            false,
            array(),
            BeRocket_AJAX_filters_version
        );

        if ( file_exists( $ion_js_path ) ) {
            wp_register_script(
                self::PREVIEW_ION_SCRIPT_HANDLE,
                $plugin_url . 'template_styles/js/ion.rangeSlider.min.js',
                array( 'jquery' ),
                filemtime( $ion_js_path ),
                true
            );
        }

        wp_register_script(
            self::PREVIEW_RUNTIME_SCRIPT_HANDLE,
            $plugin_url . 'includes/gutenberg/preview.js',
            array_values( array_filter(
                array(
                    'jquery',
                    'jquery-ui-slider',
                    'select2',
                    file_exists( $ion_js_path ) ? self::PREVIEW_ION_SCRIPT_HANDLE : '',
                    'wp-polyfill-inert',
                )
            ) ),
            file_exists( $preview_js_path ) ? filemtime( $preview_js_path ) : BeRocket_AJAX_filters_version,
            true
        );
        wp_register_script(
            self::EDITOR_SCRIPT_HANDLE,
            $plugin_url . 'includes/gutenberg/editor.js',
            array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-polyfill-inert', 'wp-server-side-render' ),
            file_exists( $editor_js_path ) ? filemtime( $editor_js_path ) : BeRocket_AJAX_filters_version,
            false
        );

        $this->editor_assets_registered = true;
    }

    /**
     * Sends editor configuration only after WordPress has established the
     * current editor user.  In particular, avoid loading every source post on
     * public requests merely to register an editor asset.
     */
    protected function localize_editor_data() {
        if ( $this->editor_data_localized ) {
            return;
        }

        $can_use_blocks = $this->can_use_gutenberg_blocks();
        $data = array(
            'canUseBlocks' => $can_use_blocks,
            'blocks'       => $this->get_editor_blocks_config( $can_use_blocks ),
            'strings'      => array(
                'previewUnavailable' => __( 'The selected item cannot be previewed in the current editor context.', 'BeRocket_AJAX_domain' ),
                'accessDenied'       => __( 'You are not allowed to use BeRocket filter blocks.', 'BeRocket_AJAX_domain' ),
            ),
        );

        wp_localize_script( self::EDITOR_SCRIPT_HANDLE, 'bapfGutenberg', $data );
        $this->editor_data_localized = true;
    }

    /**
     * Loads block-editor controls and localizes their selector data. The CSS
     * and visual-only hydrator are also queued here for non-iframed editors.
     * The same assets are separately queued from enqueue_block_assets so
     * WordPress copies them into the iframed editor canvas.
     */
    public function enqueue_editor_assets() {
        if ( ! $this->can_register_blocks() ) {
            return;
        }

        $this->register_editor_assets();
        $this->localize_editor_data();

        if ( ! $this->can_use_gutenberg_blocks() ) {
            return;
        }

        $this->prepare_editor_preview_assets();
        $this->enqueue_preview_assets();
    }

    /**
     * Queues visual preview assets in the iframe document too. WordPress
     * excludes editorScript assets while it builds that document, but it runs
     * enqueue_block_assets and copies its styles/scripts into the canvas.
     *
     * The callback deliberately does nothing on the frontend: published
     * blocks continue to use the established frontend asset lifecycle.
     */
    public function enqueue_editor_preview_assets() {
        if ( ! is_admin() || ! $this->can_register_blocks() || ! $this->can_use_gutenberg_blocks() ) {
            return;
        }

        $this->register_editor_assets();
        $this->prepare_editor_preview_assets();
        $this->enqueue_preview_assets();
    }

    /**
     * Includes the full visual stylesheet stack plus a small isolated
     * hydrator. It deliberately does not enqueue berocket_aapf_widget-script:
     * that script owns AJAX, URLs, and product DOM outside the preview.
     */
    protected function enqueue_preview_assets() {
        wp_enqueue_style( self::PREVIEW_STYLE_HANDLE );
        wp_enqueue_script( self::PREVIEW_RUNTIME_SCRIPT_HANDLE );
    }

    /**
     * Registers legacy handles only inside an editor request, then extends the
     * dedicated preview-style dependency graph with all template styles. Calling the
     * template-style loader during init would otherwise update its legacy
     * option on every frontend request just because the dynamic blocks exist.
     */
    protected function prepare_editor_preview_assets() {
        $this->ensure_frontend_assets_registered();
        $this->register_editor_preview_common_styles();
        $this->register_optional_editor_preview_styles();
        $this->register_editor_preview_fontawesome();
        $this->register_editor_preview_template_styles();
        $this->add_editor_preview_custom_css();
    }

    /**
     * Optional layers register themselves through the normal frontend-assets
     * hook. Add only layers that are actually present, so the shared file
     * works unchanged in free, paid, and business installations.
     */
    protected function register_optional_editor_preview_styles() {
        if ( ! wp_style_is( 'berocket_aapf_widget-style_paid', 'registered' ) ) {
            return;
        }

        $this->append_preview_style_dependencies( array( 'berocket_aapf_widget-style_paid' ) );
    }

    /**
     * Add the shared frontend stylesheet stack only after its handles are
     * registered for an authorized editor. This keeps non-manager editors
     * from resolving unavailable preview-only dependencies.
     */
    protected function register_editor_preview_common_styles() {
        $this->append_preview_style_dependencies( $this->get_editor_preview_common_style_dependencies() );
    }

    /**
     * Reuse the framework's configured Font Awesome version when templates
     * render configured before/after icons, and make it a preview-style
     * dependency so it also reaches the iframe.
     */
    protected function register_editor_preview_fontawesome() {
        if ( ! class_exists( 'BeRocket_AAPF' ) ) {
            return;
        }

        $options = BeRocket_AAPF::getInstance()->get_option();
        if ( ! empty( $options['disable_font_awesome'] ) ) {
            return;
        }

        $global_options = BeRocket_AAPF::get_global_option();
        $is_fontawesome_5 = ! empty( $global_options['fontawesome_frontend_version'] ) && 'fontawesome5' === $global_options['fontawesome_frontend_version'];
        $handle = '';
        $registration_type = '';

        if ( empty( $global_options['fontawesome_frontend_disable'] ) ) {
            $handle = $is_fontawesome_5 ? 'font-awesome-5' : 'font-awesome';
            $registration_type = $is_fontawesome_5 ? 'fa5' : 'fa4';
        } elseif ( $is_fontawesome_5 ) {
            $handle = 'font-awesome-5-compat';
            $registration_type = 'fa5c';
        }

        if ( '' === $handle ) {
            return;
        }

        BeRocket_AAPF::register_font_awesome( $registration_type );
        $this->append_preview_style_dependencies( array( $handle ) );
    }

    /**
     * Returns the common stylesheet dependencies for the editor-only
     * preparation method below. Template-specific dependencies are added
     * separately once their definitions have been loaded.
     * This is important for the iframe: enqueue_block_assets carries the
     * preview style and its dependencies into the canvas, while arbitrary
     * editor enqueue calls stay outside it.
     *
     * @return string[]
     */
    protected function get_editor_preview_common_style_dependencies() {
        $dependencies = array(
            'berocket_aapf_widget-style',
            'select2',
            'jquery-ui-datepick',
            'berocket_aapf_widget-scroll-style',
            'berocket_aapf_widget-themes',
        );

        if ( class_exists( 'BeRocket_AAPF' ) ) {
            $options = BeRocket_AAPF::getInstance()->get_option();
            if ( ! empty( $options['fixed_select2'] ) ) {
                $dependencies[] = 'br_select2';
            }
        }

        return $dependencies;
    }

    /**
     * Loads template-style definitions only for a Gutenberg editor request,
     * registers their stylesheet handles, and makes them preview-style
     * dependencies so WordPress carries them into the iframe canvas.
     */
    protected function register_editor_preview_template_styles() {
        $registry_key = $this->get_current_styles_registry_key();
        if ( isset( $this->preview_template_styles_registered[ $registry_key ] ) ) {
            return;
        }

        do_action( 'bapf_include_all_tempate_styles' );
        $styles = apply_filters( 'BeRocket_AAPF_getall_Template_Styles', array() );
        if ( ! is_array( $styles ) ) {
            $this->preview_template_styles_registered[ $registry_key ] = true;
            return;
        }

        $dependencies = array();

        foreach ( $styles as $style ) {
            if ( empty( $style['style_file'] ) || empty( $style['file'] ) ) {
                continue;
            }

            $style_path = dirname( $style['file'] ) . '/' . $style['style_file'];
            if ( ! file_exists( $style_path ) ) {
                continue;
            }

            $style_version = ! empty( $style['version'] ) ? $style['version'] : BeRocket_AJAX_filters_version;
            $handle = self::PREVIEW_TEMPLATE_STYLE_PREFIX . substr( md5( wp_normalize_path( $style_path ) . '|' . $style_version ), 0, 12 );
            wp_register_style(
                $handle,
                plugins_url( $style['style_file'], $style['file'] ),
                array(),
                $style_version
            );
            $dependencies[] = $handle;
        }

        $this->append_preview_style_dependencies( $dependencies );

        $this->preview_template_styles_registered[ $registry_key ] = true;
    }

    /**
     * @return string[]
     */
    protected function get_registered_preview_style_dependencies() {
        global $wp_styles;

        if (
            isset( $wp_styles->registered[ self::PREVIEW_STYLE_HANDLE ] )
            && is_array( $wp_styles->registered[ self::PREVIEW_STYLE_HANDLE ]->deps )
        ) {
            return $wp_styles->registered[ self::PREVIEW_STYLE_HANDLE ]->deps;
        }

        return array();
    }

    /**
     * @param string[] $dependencies Style handles.
     */
    protected function append_preview_style_dependencies( $dependencies ) {
        if ( ! is_array( $dependencies ) ) {
            return;
        }

        global $wp_styles;
        if ( ! isset( $wp_styles->registered[ self::PREVIEW_STYLE_HANDLE ] ) ) {
            return;
        }

        $current_dependencies = $this->get_registered_preview_style_dependencies();
        $wp_styles->registered[ self::PREVIEW_STYLE_HANDLE ]->deps = array_values(
            array_unique( array_merge( $current_dependencies, $dependencies ) )
        );
    }

    /**
     * @return string
     */
    protected function get_current_styles_registry_key() {
        global $wp_styles;

        return is_object( $wp_styles ) ? spl_object_hash( $wp_styles ) : '';
    }

    /**
     * Frontend user CSS is normally emitted at wp_footer. Gutenberg previews
     * have no such footer path, so attach the same trusted site setting to the
     * preview-style handle and let WordPress deliver it into the iframe.
     */
    protected function add_editor_preview_custom_css() {
        if ( ! class_exists( 'BeRocket_AAPF' ) ) {
            return;
        }

        $registry_key = $this->get_current_styles_registry_key();
        if ( isset( $this->preview_custom_css_added[ $registry_key ] ) ) {
            return;
        }

        $css = BeRocket_AAPF::getInstance()->br_custom_user_css();
        if ( is_string( $css ) && '' !== trim( $css ) ) {
            wp_add_inline_style( self::PREVIEW_STYLE_HANDLE, $css );
        }

        $this->preview_custom_css_added[ $registry_key ] = true;
    }

    /**
     * Builds SelectControl options from only valid, publishable source posts.
     *
     * @param string $post_type Source CPT.
     * @param string $empty_label Empty SelectControl label.
     * @return array
     */
    protected function get_post_options( $post_type, $empty_label ) {
        $cache_key         = $this->get_post_options_cache_key( $post_type );
        $cache_context_key = $this->get_post_options_cache_context_key( $post_type );
        $cache_version     = $this->get_post_options_cache_version( $post_type );
        $cached_value      = get_transient( $cache_key );
        $cached_options    = array();

        if (
            is_array( $cached_value )
            && isset( $cached_value['version'], $cached_value['contexts'] )
            && (string) $cached_value['version'] === $cache_version
            && is_array( $cached_value['contexts'] )
        ) {
            $cached_options = $cached_value['contexts'];

            if ( isset( $cached_options[ $cache_context_key ] ) && is_array( $cached_options[ $cache_context_key ] ) ) {
                return $cached_options[ $cache_context_key ];
            }
        }

        $options = array(
            array(
                'label' => $empty_label,
                'value' => 0,
            ),
        );
        $posts = get_posts(
            array(
                'post_type'              => $post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'orderby'                => 'title',
                'order'                  => 'ASC',
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'suppress_filters'       => false,
            )
        );

        foreach ( $posts as $post_id ) {
            $post_id = absint( $post_id );
            if ( ! $this->is_valid_source_post( $post_id, $post_type ) ) {
                continue;
            }

            $options[] = array(
                'label' => sprintf( '%1$s (ID: %2$d)', get_the_title( $post_id ), $post_id ),
                'value' => (int) $post_id,
            );
        }

        $cached_options[ $cache_context_key ] = $options;
        set_transient(
            $cache_key,
            array(
                'version'  => $cache_version,
                'contexts' => $cached_options,
            ),
            DAY_IN_SECONDS
        );

        return $options;
    }

    /**
     * Clears the relevant selector cache after a source post changes. Dynamic
     * save_post hooks cover creation, edits, publish changes, trash and
     * restore; before_delete_post covers permanent deletion.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object when available.
     */
    public function invalidate_post_options_cache( $post_id, $post = null ) {
        $post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );

        if ( ! in_array( $post_type, $this->get_source_post_types(), true ) ) {
            return;
        }

        /*
         * A generation prevents a concurrent request that began before this
         * change from restoring its now-stale list after the transient is
         * deleted. Its old generation will be ignored on the next read.
         */
        $cache_version_option = $this->get_post_options_cache_version_option_name( $post_type );
        $cache_version        = $this->generate_post_options_cache_version();

        if ( ! add_option( $cache_version_option, $cache_version, '', false ) ) {
            update_option( $cache_version_option, $cache_version, false );
        }

        delete_transient( $this->get_post_options_cache_key( $post_type ) );
    }

    /**
     * Calls an existing shortcode after validating the saved post reference.
     */
    protected function render_selected_post( $attributes, $attribute_name, $post_type, $shortcode, $shortcode_attribute ) {
        $attributes = is_array( $attributes ) ? $attributes : array();
        $post_id    = isset( $attributes[ $attribute_name ] ) ? absint( $attributes[ $attribute_name ] ) : 0;

        if ( ! $this->is_valid_source_post( $post_id, $post_type ) ) {
            return $this->render_preview_fallback( 0, $post_type );
        }

        $this->ensure_filter_renderer_loaded();
        $this->ensure_frontend_assets_registered();
        $html = do_shortcode( '[' . $shortcode . ' ' . $shortcode_attribute . '="' . $post_id . '"]' );

        if ( $this->is_block_renderer_preview_request ) {
            $html = $this->sanitize_rest_preview_html( $html );
        }

        if ( '' === trim( $html ) ) {
            return $this->render_preview_fallback( $post_id, $post_type, true );
        }

        $wrapper_attributes = get_block_wrapper_attributes(
            array(
                'class' => 'berocket-aapf-gutenberg-block',
            )
        );

        return '<div ' . $wrapper_attributes . '>' . $html . '</div>';
    }

    /**
     * ServerSideRender assigns the REST response with innerHTML. Frontend
     * output intentionally continues through the legacy renderer unchanged,
     * but the editor preview must not execute markup supplied by a template or
     * filter configuration. The allowlist preserves the visual filter controls
     * while excluding executable elements and event-handler attributes.
     *
     * @param string $html Legacy shortcode HTML.
     * @return string
     */
    protected function sanitize_rest_preview_html( $html ) {
        if ( ! is_string( $html ) || '' === $html ) {
            return '';
        }

        /*
         * wp_kses() removes tags but keeps their text content. Remove complete
         * legacy initialization scripts and custom style blocks first, so they
         * neither run nor appear as text in the editor preview.
         */
        $html = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $html );
        $html = $this->normalize_rest_preview_background_styles( $html );

        $html = wp_kses( $html, $this->get_rest_preview_allowed_html(), array( 'http', 'https' ) );

        return $this->sanitize_rest_preview_inline_styles( $html );
    }

    /**
     * The bundled Image template uses `background: url(...)`, whereas core
     * safe CSS only retains the corresponding background-image declaration.
     * Preserve that one visual detail before KSES validates its URL and drops
     * the original shorthand declaration.
     *
     * @param string $html Legacy preview HTML without script/style blocks.
     * @return string
     */
    protected function normalize_rest_preview_background_styles( $html ) {
        $processor = new WP_HTML_Tag_Processor( $html );

        while ( $processor->next_tag() ) {
            $style = $processor->get_attribute( 'style' );
            if ( ! is_string( $style ) || ! preg_match_all( '/background\\s*:\\s*url\\(\\s*([\'\"]?)([^\'\")]+)\\1\\s*\\)/i', $style, $matches ) ) {
                continue;
            }

            foreach ( $matches[2] as $url ) {
                $style .= ';background-image: url(' . trim( $url ) . ')';
            }

            $processor->set_attribute( 'style', $style );
        }

        return $processor->get_updated_html();
    }

    /**
     * Keep the small set of inline declarations used by the bundled Color and
     * Image templates. Core KSES removes script-capable CSS values first; this
     * second allowlist prevents preview-only markup from repositioning or
     * intercepting the editor UI.
     *
     * @param string $html KSES-sanitized preview HTML.
     * @return string
     */
    protected function sanitize_rest_preview_inline_styles( $html ) {
        $processor = new WP_HTML_Tag_Processor( $html );

        while ( $processor->next_tag() ) {
            $style = $processor->get_attribute( 'style' );
            if ( ! is_string( $style ) || '' === $style ) {
                continue;
            }

            $style = $this->sanitize_rest_preview_inline_style( $style );
            if ( '' === $style ) {
                $processor->remove_attribute( 'style' );
            } else {
                $processor->set_attribute( 'style', $style );
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * @param string $style KSES-sanitized inline CSS.
     * @return string
     */
    protected function sanitize_rest_preview_inline_style( $style ) {
        $allowed_properties = array(
            'background',
            'background-color',
            'background-image',
            'background-size',
            'display',
            'font-size',
            'height',
            'line-height',
            'width',
        );
        $declarations = array();

        foreach ( explode( ';', $style ) as $declaration ) {
            $parts = explode( ':', $declaration, 2 );
            if ( 2 !== count( $parts ) ) {
                continue;
            }

            $property = strtolower( trim( $parts[0] ) );
            $value    = trim( $parts[1] );
            if ( ! in_array( $property, $allowed_properties, true ) || '' === $value ) {
                continue;
            }

            $declarations[] = $property . ': ' . $value;
        }

        return implode( '; ', $declarations );
    }

    /**
     * @return array<string, array<string, bool>>
     */
    protected function get_rest_preview_allowed_html() {
        $global_attributes = array(
            'aria-checked'     => true,
            'aria-controls'    => true,
            'aria-current'     => true,
            'aria-describedby' => true,
            'aria-disabled'    => true,
            'aria-expanded'    => true,
            'aria-hidden'      => true,
            'aria-label'       => true,
            'aria-labelledby'  => true,
            'aria-live'        => true,
            'aria-selected'    => true,
            'class'            => true,
            'data-*'           => true,
            'dir'              => true,
            'hidden'           => true,
            'id'               => true,
            'lang'             => true,
            'role'             => true,
            'style'            => true,
            'tabindex'         => true,
            'title'            => true,
        );
        $allowed_html = array();
        $content_tags = array(
            'b',
            'br',
            'div',
            'dl',
            'dt',
            'dd',
            'em',
            'fieldset',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'hr',
            'i',
            'ins',
            'legend',
            'li',
            'ol',
            'p',
            's',
            'small',
            'span',
            'strike',
            'strong',
            'sub',
            'sup',
            'u',
            'ul',
        );

        foreach ( $content_tags as $tag ) {
            $allowed_html[ $tag ] = $global_attributes;
        }

        $allowed_html['a'] = array_merge(
            $global_attributes,
            array(
                'download' => true,
                'href'     => true,
                'rel'      => true,
                'target'   => true,
            )
        );
        $allowed_html['button'] = array_merge(
            $global_attributes,
            array(
                'disabled' => true,
                'name'     => true,
                'type'     => true,
                'value'    => true,
            )
        );
        $allowed_html['form'] = $global_attributes;
        $allowed_html['img'] = array_merge(
            $global_attributes,
            array(
                'alt'     => true,
                'height'  => true,
                'loading' => true,
                'src'     => true,
                'width'   => true,
            )
        );
        $allowed_html['input'] = array_merge(
            $global_attributes,
            array(
                'autocomplete' => true,
                'checked'      => true,
                'disabled'     => true,
                'max'          => true,
                'min'          => true,
                'multiple'     => true,
                'name'         => true,
                'placeholder'  => true,
                'readonly'     => true,
                'required'     => true,
                'size'         => true,
                'step'         => true,
                'type'         => true,
                'value'        => true,
            )
        );
        $allowed_html['label'] = array_merge(
            $global_attributes,
            array(
                'for' => true,
            )
        );
        $allowed_html['option'] = array_merge(
            $global_attributes,
            array(
                'disabled' => true,
                'label'    => true,
                'selected' => true,
                'value'    => true,
            )
        );
        $allowed_html['optgroup'] = array_merge(
            $global_attributes,
            array(
                'disabled' => true,
                'label'    => true,
            )
        );
        $allowed_html['select'] = array_merge(
            $global_attributes,
            array(
                'autocomplete' => true,
                'disabled'     => true,
                'multiple'     => true,
                'name'         => true,
                'required'     => true,
                'size'         => true,
            )
        );
        $allowed_html['textarea'] = array_merge(
            $global_attributes,
            array(
                'cols'        => true,
                'disabled'    => true,
                'name'        => true,
                'placeholder' => true,
                'readonly'    => true,
                'rows'        => true,
            )
        );

        return $allowed_html;
    }

    /**
     * A missing selection remains empty on the frontend, while a REST preview
     * gives an editor a useful explanation rather than a blank block.
     */
    protected function render_preview_fallback( $post_id, $post_type, $source_is_valid = false ) {
        if ( ! $this->is_block_renderer_preview_request ) {
            return '';
        }

        $label = 'br_product_filter' === $post_type
            ? __( 'Filter', 'BeRocket_AJAX_domain' )
            : __( 'Filter group', 'BeRocket_AJAX_domain' );
        $name = $source_is_valid && $post_id ? get_the_title( $post_id ) : '';
        $text = $name
            ? sprintf( __( '%1$s “%2$s” has no visible preview in this editor context.', 'BeRocket_AJAX_domain' ), $label, $name )
            : sprintf( __( 'Select a %s to preview it here.', 'BeRocket_AJAX_domain' ), strtolower( $label ) );

        return '<div ' . get_block_wrapper_attributes( array( 'class' => 'berocket-aapf-gutenberg-preview-notice' ) ) . '><p>' . esc_html( $text ) . '</p></div>';
    }

    /**
     * Source posts are configuration objects and must be published and of the
     * expected CPT before their shortcode is invoked.
     */
    protected function is_valid_source_post( $post_id, $post_type ) {
        return $post_id && $post_type === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
    }

    /**
     * The normal frontend registers these on the wp hook. REST block previews
     * need registration earlier because no wp hook runs for that endpoint.
     */
    protected function ensure_frontend_assets_registered() {
        if (
            ( ! wp_script_is( 'berocket_aapf_widget-script', 'registered' )
                || ! wp_style_is( 'berocket_aapf_widget-style', 'registered' ) )
            && class_exists( 'BeRocket_AAPF' )
        ) {
            BeRocket_AAPF::getInstance()->register_frontend_assets();
        }
    }

    /**
     * Shop managers may reuse published filters in content without receiving
     * the separate capabilities required to edit their configuration.
     * WooCommerce assigns manage_woocommerce to its administrators too.
     */
    protected function can_use_gutenberg_blocks() {
        $can_use_blocks = current_user_can( 'manage_woocommerce' );

        return (bool) apply_filters( 'bapf_gutenberg_blocks_user_can_use', $can_use_blocks );
    }

    /**
     * @return string[]
     */
    protected function get_block_names() {
        return array(
            'berocket/single-filter',
            'berocket/filter-group',
        );
    }

    /**
     * block.json is registered by WordPress before the editor script runs.
     * Only editor-specific data belongs here; the client obtains metadata
     * (including translated title, icon and attributes) from that registration.
     *
     * @param bool $include_options Whether selector options may be exposed.
     * @return array[]
     */
    protected function get_editor_blocks_config( $include_options ) {
        $blocks = array(
            array(
                'name'             => 'berocket/single-filter',
                'selectionMessage' => __( 'Select a filter to preview it here.', 'BeRocket_AJAX_domain' ),
                'options'          => array(),
            ),
            array(
                'name'             => 'berocket/filter-group',
                'selectionMessage' => __( 'Select a filter group to preview it here.', 'BeRocket_AJAX_domain' ),
                'options'          => array(),
            ),
        );

        if ( ! $include_options ) {
            return $blocks;
        }

        $blocks[0]['options'] = $this->get_post_options( 'br_product_filter', __( '-- Select a filter --', 'BeRocket_AJAX_domain' ) );
        $blocks[1]['options'] = $this->get_post_options( 'br_filters_group', __( '-- Select a filter group --', 'BeRocket_AJAX_domain' ) );

        return $blocks;
    }

    /**
     * @return string[]
     */
    protected function get_source_post_types() {
        return array(
            'br_product_filter',
            'br_filters_group',
        );
    }

    /**
     * A stable cache key lets one invalidation remove every locale variant.
     *
     * @param string $post_type Source CPT.
     * @return string
     */
    protected function get_post_options_cache_key( $post_type ) {
        return self::SELECTOR_CACHE_PREFIX . sanitize_key( $post_type );
    }

    /**
     * A cache version is stored separately from the transient. Reading it
     * before the source query makes cache invalidation safe across concurrent
     * editor requests.
     *
     * @param string $post_type Source CPT.
     * @return string
     */
    protected function get_post_options_cache_version( $post_type ) {
        $option_name = $this->get_post_options_cache_version_option_name( $post_type );
        $version     = get_option( $option_name, '' );

        if ( is_string( $version ) && '' !== $version ) {
            return $version;
        }

        $version = $this->generate_post_options_cache_version();

        if ( ! add_option( $option_name, $version, '', false ) ) {
            $stored_version = get_option( $option_name, '' );
            if ( is_string( $stored_version ) && '' !== $stored_version ) {
                return $stored_version;
            }
        }

        return $version;
    }

    /**
     * @param string $post_type Source CPT.
     * @return string
     */
    protected function get_post_options_cache_version_option_name( $post_type ) {
        return self::SELECTOR_CACHE_VERSION_PREFIX . sanitize_key( $post_type );
    }

    /**
     * @return string
     */
    protected function generate_post_options_cache_version() {
        return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'bapf-', true );
    }

    /**
     * Filter titles can be language-specific, so each locale/language keeps a
     * separate entry inside the same transient.
     *
     * @param string $post_type Source CPT.
     * @return string
     */
    protected function get_post_options_cache_context_key( $post_type ) {
        $context = array(
            'locale'             => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
            'wpml_language'      => apply_filters( 'wpml_current_language', null ),
            'wpm_language'       => function_exists( 'wpm_get_language' ) ? wpm_get_language() : '',
            'berocket_language'  => function_exists( 'br_get_current_language_code' ) ? br_get_current_language_code() : '',
        );

        if ( function_exists( 'pll_current_language' ) ) {
            $context['polylang_language'] = pll_current_language( 'slug' );
        }

        $context = apply_filters( 'bapf_gutenberg_post_options_cache_context', $context, $post_type );

        return md5( maybe_serialize( $context ) );
    }

    /**
     * @param WP_REST_Request $request REST request.
     * @return bool
     */
    protected function is_block_renderer_request( $request ) {
        if ( ! $request instanceof WP_REST_Request || 0 !== strpos( $request->get_route(), '/wp/v2/block-renderer/' ) ) {
            return false;
        }

        return in_array( $request->get_param( 'name' ), $this->get_block_names(), true );
    }

    protected function can_register_blocks() {
        return class_exists( 'BeRocket_AAPF' ) && BeRocket_AAPF::getInstance()->init_validation();
    }
}

new BeRocket_AAPF_Gutenberg();
