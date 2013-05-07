( function( $ ) {

	// Handles showing/hiding additional fields required for certain columns in the report
	var editorial_statistics_show_field = function( $checkbox, $target ) {
		if( $checkbox.attr( "checked" ) == 'checked' )
			$target.show();
		else
			$target.hide();
	}
	
	// Handles resetting the entire form
	var editorial_statistics_reset_options = function() {
		$( '#' + editorial_statistics.prefix + 'start_date' ).val( '' );
		$( '#' + editorial_statistics.prefix + 'end_date' ).val( '' );
		$( '.editorial-statistics-report-column' ).removeAttr( 'checked' );
		$( '#editorial_statistics_terms option:selected' ).removeAttr( 'selected' );
		$( '#editorial_statistics_terms' ).trigger( 'liszt:updated' );
		editorial_statistics_show_field( $( '#' + editorial_statistics.prefix + 'report_columns_term' ), $( '#' + editorial_statistics.prefix + 'terms_wrapper' ) );
	}
	
	// Handles setting a predefined date range
	var editorial_statistics_set_date_range = function( $range ) {
		$( '#' + editorial_statistics.prefix + 'start_date' ).val( $range.data( 'startDate' ) );
		$( '#' + editorial_statistics.prefix + 'end_date' ).val( $range.data( 'endDate' ) );
		
		// Also clear any error messages about start/end date since these are now set
		$( '#' + editorial_statistics.prefix + 'start_date' ).siblings( '.error-message' ).html( '' );
		$( '#' + editorial_statistics.prefix + 'end_date' ).siblings( '.error-message' ).html( '' );
	}
	
	$( document ).ready( function() {
	
		// Initialize the datepickers
		$( '#' + editorial_statistics.prefix + 'start_date' ).datepicker();
		$( '#' + editorial_statistics.prefix + 'end_date' ).datepicker();
		
		// Initialize chosen for term selection
		$( '#' + editorial_statistics.prefix + 'terms' ).chosen();
		
		// Clear any error messages after fields are selected via chosen
		$( '#' + editorial_statistics.prefix + 'terms' ).chosen().change( function() {
			$( '#' + editorial_statistics.prefix + 'terms' ).siblings( '.error-message' ).html( '' );
		} );
		
		// Handle displaying the taxonomy selection when Term is selected as a column
		$( '#editorial_statistics_report_columns_term' ).on( 'click', function( event ) {
			editorial_statistics_show_field( $(this), $( '#' + editorial_statistics.prefix + 'terms_wrapper' ) );
		} );
		
		// Handle setting a predefined date range when a link is clicked
		$( '.editorial-statistics-date-range' ).on( 'click', function( event ) {
			event.preventDefault();
			editorial_statistics_set_date_range( $(this) );
		} );

		// Add jQuery form validation
		$( '#editorial_statistics_form' ).validate({
			groups: {
				names: "editorial_statistics_report_columns[]"
			},
			rules: {
				editorial_statistics_start_date: "required",
				editorial_statistics_end_date: "required",
				editorial_statistics_terms: {
					required: "#editorial_statistics_report_columns_term:checked"
				},
				'editorial_statistics_report_columns[]': "required"
			},
			errorPlacement: function( error, element ) {
				error.appendTo( element.siblings( '.error-message' ) );
			},
			ignore: ".default" // for chosen.js compatibility
		});
		
		// Handle resetting all options on the form
		$( '#editorial_statistics_reset' ).on( 'click', function( event ) {
			editorial_statistics_reset_options();
		} );
		
		// Determine whether or not to display the taxonomy selection field on load
		editorial_statistics_show_field( $( '#' + editorial_statistics.prefix + 'report_columns_term' ), $( '#' + editorial_statistics.prefix + 'terms_wrapper' ) );
		
	} );

} )( jQuery );