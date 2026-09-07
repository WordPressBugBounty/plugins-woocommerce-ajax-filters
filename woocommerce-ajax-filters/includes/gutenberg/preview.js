( function( $ ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var previewSelector = '.berocket-aapf-gutenberg-preview';
	var hydrationQueued = false;

	function toNumber( value, fallback ) {
		var number = Number( value );

		return isFinite( number ) ? number : fallback;
	}

	function escapeHtml( value ) {
		return String( value ).replace( /[&<>"']/g, function( character ) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[ character ];
		} );
	}

	function getDisplayValue( $slider, value ) {
		var attributes = $slider.data( 'attr' );
		var attribute;

		if ( attributes && 'object' === typeof attributes ) {
			attribute = attributes[ value ];
			if ( undefined === attribute ) {
				attribute = attributes[ String( value ) ];
			}

			if ( attribute && 'object' === typeof attribute && undefined !== attribute.n ) {
				return attribute.n;
			}
		}

		return value;
	}

	function updateClassicSliderValues( $slider, values ) {
		var $container = $slider.closest( '.bapf_slidr_jqrui' );
		var from = getDisplayValue( $slider, values[ 0 ] );
		var to = getDisplayValue( $slider, values[ 1 ] );

		$container.find( '.bapf_from span.bapf_val' ).text( from );
		$container.find( '.bapf_to span.bapf_val' ).text( to );
		$container.find( '.bapf_from input[type="text"]' ).val( from );
		$container.find( '.bapf_to input[type="text"]' ).val( to );
	}

	function removeIonSliderDataOptions( $slider ) {
		var optionNames = [
			'skin',
			'type',
			'min',
			'max',
			'from',
			'to',
			'step',
			'minInterval',
			'maxInterval',
			'dragInterval',
			'values',
			'fromFixed',
			'fromMin',
			'fromMax',
			'fromShadow',
			'toFixed',
			'toMin',
			'toMax',
			'toShadow',
			'prettifyEnabled',
			'prettifySeparator',
			'forceEdges',
			'keyboard',
			'grid',
			'gridMargin',
			'gridNum',
			'gridSnap',
			'hideMinMax',
			'hideFromTo',
			'prefix',
			'postfix',
			'maxPostfix',
			'decorateBoth',
			'valuesSeparator',
			'inputValuesSeparator',
			'disable',
			'block',
			'extraClasses'
		];

		$.each( optionNames, function( index, optionName ) {
			var attributeName = 'data-' + optionName.replace( /([A-Z])/g, '-$1' ).toLowerCase();

			/*
			 * Ion RangeSlider applies data-* values after the explicit options and
			 * writes several of them through .html().  A filter configuration can
			 * contain label markup, so keep the inert preview on our known-safe
			 * numeric configuration instead of passing that markup to Ion.
			 */
			$slider.removeData( optionName ).removeAttr( attributeName );
		} );
	}

	function hydrateClassicSliders( $preview ) {
		if ( 'function' !== typeof $.fn.slider ) {
			return;
		}

		$preview.find( '.bapf_slidr_jqrui' ).each( function() {
			var $container = $( this );
			var $slider = $container.find( '.bapf_slidr_main' ).first();
			var min;
			var max;
			var start;
			var end;
			var step;

			if ( ! $slider.length || $container.hasClass( 'bapf-gutenberg-preview-ready' ) ) {
				return;
			}

			min = toNumber( $slider.data( 'min' ), 0 );
			max = toNumber( $slider.data( 'max' ), min );
			start = toNumber( $slider.data( 'start' ), min );
			end = toNumber( $slider.data( 'end' ), max );
			step = toNumber( $slider.data( 'step' ), 1 );

			if ( max < min || step <= 0 ) {
				return;
			}

			$slider.slider( {
				range: true,
				min: min,
				max: max,
				values: [ Math.max( min, Math.min( start, max ) ), Math.max( min, Math.min( end, max ) ) ],
				step: step,
				create: function() {
					updateClassicSliderValues( $slider, $slider.slider( 'values' ) );
				},
				slide: function( event, ui ) {
					updateClassicSliderValues( $slider, ui.values );
				}
			} );

			$container.addClass( 'bapf_slidr_ready bapf-gutenberg-preview-ready' );
			$slider.find( '.ui-slider-handle' ).attr( 'tabindex', '-1' );
		} );
	}

	function hydrateIonSliders( $preview ) {
		if ( 'function' !== typeof $.fn.ionRangeSlider ) {
			return;
		}

		$preview.find( '.bapf_slidr_ion' ).each( function() {
			var $container = $( this );
			var $slider = $container.find( '.bapf_slidr_main' ).first();
			var min;
			var max;
			var from;
			var to;
			var step;

			if ( ! $slider.length || $container.hasClass( 'bapf-gutenberg-preview-ready' ) ) {
				return;
			}

			min = toNumber( $slider.data( 'min' ), 0 );
			max = toNumber( $slider.data( 'max' ), min );
			from = toNumber( $slider.data( 'start' ), min );
			to = toNumber( $slider.data( 'end' ), max );
			step = toNumber( $slider.data( 'step' ), 1 );

			if ( max < min || step <= 0 ) {
				return;
			}

			removeIonSliderDataOptions( $slider );

			$slider.ionRangeSlider( {
				type: 'double',
				min: min,
				max: max,
				from: Math.max( min, Math.min( from, max ) ),
				to: Math.max( min, Math.min( to, max ) ),
				step: step,
				grid: false,
				force_edges: true,
				keyboard: false,
				values: [],
				prettify: function( value ) {
					return escapeHtml( getDisplayValue( $slider, value ) );
				}
			} );

			$container.addClass( 'bapf_slidr_ready bapf-gutenberg-preview-ready' );
			$container.find( '.irs [tabindex]' ).attr( 'tabindex', '-1' );
		} );
	}

	function hydrateSelect2( $preview ) {
		if ( 'function' !== typeof $.fn.select2 ) {
			return;
		}

		$preview.find( '.bapf_select2' ).each( function() {
			var $select = $( this );
			var selectOptions = {
				width: '100%',
				theme: $select.data( 'theme' ) || 'default',
				dropdownParent: $preview
			};

			if ( $select.data( 'select2' ) || $select.hasClass( 'select2-hidden-accessible' ) ) {
				return;
			}

			if ( $select.prop( 'multiple' ) ) {
				selectOptions.placeholder = $select.data( 'placeholder' );
			}

			$select.select2( selectOptions );
		} );
	}

	function preventPreviewFocus( $preview ) {
		$preview.find( 'a, button, input, select, textarea, [tabindex]' ).attr( 'tabindex', '-1' );
	}

	function hydratePreview( $preview ) {
		hydrateClassicSliders( $preview );
		hydrateIonSliders( $preview );
		hydrateSelect2( $preview );
		preventPreviewFocus( $preview );
	}

	function hydrateAllPreviews() {
		$( previewSelector ).each( function() {
			hydratePreview( $( this ) );
		} );
	}

	function scheduleHydration() {
		if ( hydrationQueued ) {
			return;
		}

		hydrationQueued = true;
		window.setTimeout( function() {
			hydrationQueued = false;
			hydrateAllPreviews();
		}, 0 );
	}

	function nodeTouchesPreview( node ) {
		if ( ! node || 1 !== node.nodeType ) {
			return false;
		}

		return (
			( node.matches && node.matches( previewSelector ) ) ||
			( node.closest && node.closest( previewSelector ) ) ||
			( node.querySelector && node.querySelector( previewSelector ) )
		);
	}

	function observePreviews() {
		var observer;

		hydrateAllPreviews();

		if ( ! window.MutationObserver || ! document.documentElement ) {
			return;
		}

		observer = new window.MutationObserver( function( mutations ) {
			var changed = false;

			mutations.forEach( function( mutation ) {
				var index;

				if ( changed ) {
					return;
				}

				if ( nodeTouchesPreview( mutation.target ) ) {
					changed = true;
					return;
				}

				for ( index = 0; index < mutation.addedNodes.length; index++ ) {
					if ( nodeTouchesPreview( mutation.addedNodes[ index ] ) ) {
						changed = true;
						return;
					}
				}
			} );

			if ( changed ) {
				scheduleHydration();
			}
		} );

		observer.observe( document.documentElement, {
			childList: true,
			subtree: true
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', observePreviews );
	} else {
		observePreviews();
	}
}( window.jQuery ) );
