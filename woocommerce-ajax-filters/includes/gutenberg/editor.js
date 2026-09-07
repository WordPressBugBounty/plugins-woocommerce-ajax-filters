( function( blocks, blockEditor, components, element, hooks, serverSideRender, settings ) {
	'use strict';

	if ( ! blocks || ! blockEditor || ! components || ! element ) {
		return;
	}

	var createElement = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var Placeholder = components.Placeholder;
	var SelectControl = components.SelectControl;
	var ServerSideRender = serverSideRender && ( serverSideRender.default || serverSideRender );
	var blockSettings = settings && Array.isArray( settings.blocks ) ? settings.blocks : [];
	var strings = settings && settings.strings ? settings.strings : {};
	var canUseBlocks = !! ( settings && settings.canUseBlocks );
	var definitions = blockSettings.filter( function( definition ) {
		return definition && 'string' === typeof definition.name;
	} );
	var definitionNames = definitions.map( function( definition ) {
		return definition.name;
	} );

	function getSelectionAttribute( blockType ) {
		var attributes = blockType && blockType.attributes && 'object' === typeof blockType.attributes ? blockType.attributes : {};
		var names = Object.keys( attributes ).filter( function( name ) {
			return attributes[ name ] && 'number' === attributes[ name ].type;
		} );

		return 1 === names.length ? names[ 0 ] : '';
	}

	/*
	 * block.json is bootstrapped by WordPress before this editor script runs.
	 * The runtime supports override keeps the client consistent with the
	 * server-side allowed_block_types_all policy without copying metadata.
	 */
	if ( hooks && 'function' === typeof hooks.addFilter ) {
		hooks.addFilter(
			'blocks.registerBlockType',
			'berocket/aapf-gutenberg-inserter',
			function( blockTypeSettings, blockName ) {
				if ( -1 === definitionNames.indexOf( blockName ) ) {
					return blockTypeSettings;
				}

				return Object.assign( {}, blockTypeSettings, {
					supports: Object.assign( {}, blockTypeSettings.supports || {}, {
						inserter: canUseBlocks
					} )
				} );
			}
		);
	}

	function getConfiguration( definition ) {
		var blockType = blocks.getBlockType( definition.name );

		return {
			attribute: getSelectionAttribute( blockType ),
			label: blockType && blockType.title ? blockType.title : '',
			options: Array.isArray( definition.options ) ? definition.options : []
		};
	}

	function getSelectionMessage( definition ) {
		return definition.selectionMessage || '';
	}

	function getPreviewUnavailableMessage() {
		return strings.previewUnavailable || '';
	}

	function getAccessDeniedMessage() {
		return strings.accessDenied || '';
	}

	/*
	 * The block registry normalizes metadata icons into an object such as
	 * { src: 'filter' }. Placeholder expects the icon source itself (or a
	 * React element), rather than that descriptor object.
	 */
	function getBlockIcon( blockType ) {
		var icon = blockType && blockType.icon;

		if ( icon && 'object' === typeof icon && Object.prototype.hasOwnProperty.call( icon, 'src' ) ) {
			return icon.src;
		}

		return icon;
	}

	function normalizeId( value ) {
		var id = Number( value );

		if ( id > 0 && Math.floor( id ) === id ) {
			return id;
		}

		return undefined;
	}

	function isSelected( value ) {
		return undefined !== normalizeId( value );
	}

	function getOptions( configuration, selectionMessage ) {
		var options = configuration.options.map( function( option ) {
			return {
				label: option && undefined !== option.label ? String( option.label ) : '',
				value: option && undefined !== option.value ? String( option.value ) : ''
			};
		} );

		if ( ! options.length ) {
			options.push( {
				label: selectionMessage,
				value: ''
			} );
		}

		return options;
	}

	function getEmptyOptionValue( options ) {
		var index;

		for ( index = 0; index < options.length; index++ ) {
			if ( ! isSelected( options[ index ].value ) ) {
				return options[ index ].value;
			}
		}

		return '';
	}

	function createSelectionControl( props, configuration, selectionMessage ) {
		var options = getOptions( configuration, selectionMessage );
		var selected = normalizeId( props.attributes[ configuration.attribute ] );
		var attributes = {};

		return createElement( SelectControl, {
			label: configuration.label,
			value: undefined === selected ? getEmptyOptionValue( options ) : String( selected ),
			options: options,
			onChange: function( value ) {
				attributes[ configuration.attribute ] = normalizeId( value );
				props.setAttributes( attributes );
			}
		} );
	}

	function createPreviewFallback( message ) {
		return createElement(
			'div',
			{ className: 'berocket-aapf-gutenberg-preview__unavailable' },
			createElement( 'p', null, message )
		);
	}

	function createEdit( definition ) {
		return function( props ) {
			if ( ! canUseBlocks ) {
				return createElement(
					'div',
					useBlockProps( { className: 'berocket-aapf-gutenberg-block' } ),
					createPreviewFallback( getAccessDeniedMessage() )
				);
			}

			var configuration = getConfiguration( definition );
			var selectionMessage = getSelectionMessage( definition );
			var previewUnavailableMessage = getPreviewUnavailableMessage();
			if ( ! configuration.attribute ) {
				return createElement(
					'div',
					useBlockProps( { className: 'berocket-aapf-gutenberg-block' } ),
					createPreviewFallback( previewUnavailableMessage )
				);
			}

			var selected = isSelected( props.attributes[ configuration.attribute ] );
			var blockType = blocks.getBlockType( definition.name );
			var title = blockType && blockType.title ? blockType.title : configuration.label;
			var inspector = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{
						title: configuration.label,
						initialOpen: true
					},
					createSelectionControl( props, configuration, selectionMessage )
				)
			);
			var content;

			if ( ! selected ) {
				content = createElement(
					Placeholder,
					{
						className: 'berocket-aapf-gutenberg-placeholder',
						icon: getBlockIcon( blockType ),
						label: title,
						instructions: selectionMessage
					},
					createSelectionControl( props, configuration, selectionMessage )
				);
			} else if ( 'function' === typeof ServerSideRender ) {
				/*
				 * The native inert attribute (with WordPress's inert polyfill) keeps
				 * all asynchronously rendered filter controls out of mouse, keyboard,
				 * focus, and assistive-technology interaction. The real controls are
				 * configured through this block's Inspector panel instead.
				 */
				content = createElement(
					'div',
					{
						className: 'berocket-aapf-gutenberg-preview',
						inert: ''
					},
					createElement( ServerSideRender, {
						block: definition.name,
						attributes: props.attributes,
						className: 'berocket-aapf-gutenberg-preview__content',
						EmptyResponsePlaceholder: function() {
							return createPreviewFallback( previewUnavailableMessage );
						},
						ErrorResponsePlaceholder: function() {
							return createPreviewFallback( previewUnavailableMessage );
						}
					} )
				);
			} else {
				content = createPreviewFallback( previewUnavailableMessage );
			}

			return createElement(
				'div',
				useBlockProps( { className: 'berocket-aapf-gutenberg-block' } ),
				inspector,
				content
			);
		};
	}

	definitions.forEach( function( definition ) {
		if ( blocks.getBlockType( definition.name ) ) {
			return;
		}

		blocks.registerBlockType( definition.name, {
			edit: createEdit( definition ),
			save: function() {
				return null;
			}
		} );
	} );
}( window.wp && window.wp.blocks, window.wp && window.wp.blockEditor, window.wp && window.wp.components, window.wp && window.wp.element, window.wp && window.wp.hooks, window.wp && window.wp.serverSideRender, window.bapfGutenberg ) );
