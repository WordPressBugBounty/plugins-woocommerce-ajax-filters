( function( hooks, compose, element, blockEditor, components, i18n ) {
    'use strict';

    if ( ! hooks || ! compose || ! element || ! blockEditor || ! components || ! i18n ) {
        return;
    }

    var attributeName = 'bapfApply';
    var productBlocks = [
        'woocommerce/product-collection',
        'woocommerce/product-query',
        'woocommerce/related-products',
        'woocommerce/product-new',
        'woocommerce/product-best-sellers',
        'woocommerce/product-on-sale',
        'woocommerce/product-top-rated',
        'woocommerce/product-category',
        'woocommerce/product-tag',
        'woocommerce/products-by-attribute',
        'woocommerce/handpicked-products'
    ];
    var createElement = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var __ = i18n.__;

    function isSupportedBlockName( blockName ) {
        return 'core/query' === blockName || -1 !== productBlocks.indexOf( blockName );
    }

    function isProductsBlock( props ) {
        if ( -1 !== productBlocks.indexOf( props.name ) ) {
            return true;
        }

        return 'core/query' === props.name
            && props.attributes
            && props.attributes.query
            && 'product' === props.attributes.query.postType;
    }

    hooks.addFilter(
        'blocks.registerBlockType',
        'berocket/aapf-product-query-attribute',
        function( settings, blockName ) {
            if ( ! isSupportedBlockName( blockName ) ) {
                return settings;
            }

            settings.attributes = Object.assign( {}, settings.attributes );
            settings.attributes[ attributeName ] = {
                type: 'string',
                default: 'default'
            };

            return settings;
        }
    );

    var withApplyFiltersControl = compose.createHigherOrderComponent(
        function( BlockEdit ) {
            return function( props ) {
                if ( ! isProductsBlock( props ) ) {
                    return createElement( BlockEdit, props );
                }

                return createElement(
                    Fragment,
                    null,
                    createElement( BlockEdit, props ),
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            {
                                title: __( 'BeRocket AJAX Filters', 'BeRocket_AJAX_domain' ),
                                initialOpen: false
                            },
                            createElement( SelectControl, {
                                label: __( 'Apply filters', 'BeRocket_AJAX_domain' ),
                                value: props.attributes[ attributeName ] || 'default',
                                options: [
                                    {
                                        label: __( 'Default (page query)', 'BeRocket_AJAX_domain' ),
                                        value: 'default'
                                    },
                                    {
                                        label: __( 'Enable', 'BeRocket_AJAX_domain' ),
                                        value: 'enable'
                                    },
                                    {
                                        label: __( 'Disable', 'BeRocket_AJAX_domain' ),
                                        value: 'disable'
                                    }
                                ],
                                help: __( 'Default follows an inherited page query. Enable or Disable overrides the shortcode marker for this block.', 'BeRocket_AJAX_domain' ),
                                onChange: function( value ) {
                                    props.setAttributes( ( function() {
                                        var attributes = {};
                                        attributes[ attributeName ] = value;
                                        return attributes;
                                    } )() );
                                }
                            } )
                        )
                    )
                );
            };
        },
        'withBeRocketApplyFiltersControl'
    );

    hooks.addFilter(
        'editor.BlockEdit',
        'berocket/aapf-product-query-control',
        withApplyFiltersControl
    );
} )(
    window.wp && window.wp.hooks,
    window.wp && window.wp.compose,
    window.wp && window.wp.element,
    window.wp && window.wp.blockEditor,
    window.wp && window.wp.components,
    window.wp && window.wp.i18n
);
