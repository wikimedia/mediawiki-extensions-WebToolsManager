$( function () {
	var openGraphDependentInputs = [],
		/**
		 * Run OO.ui.infuse if the element exists
		 *
		 * @param  {jQuery} $el jQuery element to infuse
		 * @return {OO.ui.Widget|null} Infused widget or null if not found
		 */
		infuseIfExists = function ( $el ) {
			if ( !$el.length ) {
				return null;
			}
			return OO.ui.infuse( $el );
		},
		/**
		 * Toggle all dependent fields based on the given value
		 *
		 * @param {OO.ui.Widget[]} fields Dependent fields
		 * @param {boolean} areEnabled Fields are enabled
		 */
		toggleDependentFields = function ( fields, areEnabled ) {
			fields.forEach( function ( fieldLayout ) {
				fieldLayout.fieldWidget.setDisabled( areEnabled );
			} );
		},
		openGraphCheckboxWidget = infuseIfExists( $( '#mw-input-wpopengraph-activate' ) );
	if ( openGraphCheckboxWidget ) {
		// Infuse all dependent inputs
		$( '.opengraph-dependent-input.oo-ui-fieldLayout' ).each( function () {
			var layout = infuseIfExists( $( this ) );
			openGraphDependentInputs.push( layout );
		} );

		// Event
		openGraphCheckboxWidget.on( 'change', function ( isSelected ) {
			toggleDependentFields( openGraphDependentInputs, !isSelected );
		} );

		// Initialize
		toggleDependentFields(
			openGraphDependentInputs,
			!openGraphCheckboxWidget.isSelected()
		);
	}
} );
