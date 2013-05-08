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
		$( '#editorial_statistics_start_date' ).val( '' );
		$( '#editorial_statistics_end_date' ).val( '' );
		$( '.editorial-statistics-report-column' ).removeAttr( 'checked' );
		$( '#editorial_statistics_terms option:selected' ).removeAttr( 'selected' );
		$( '#editorial_statistics_terms' ).trigger( 'liszt:updated' );
		editorial_statistics_show_field( $( '#editorial_statistics_report_columns_term' ), $( '#editorial_statistics_terms_wrapper' ) );
	}
	
	// Handles setting a predefined date range
	var editorial_statistics_set_date_range = function( $range ) {
		$( '#editorial_statistics_start_date' ).val( $range.data( 'startDate' ) );
		$( '#editorial_statistics_end_date' ).val( $range.data( 'endDate' ) );
		
		// Also clear any error messages about start/end date since these are now set
		$( '#editorial_statistics_start_date' ).siblings( '.error-message' ).html( '' );
		$( '#editorial_statistics_end_date' ).siblings( '.error-message' ).html( '' );
	}
	
	// Handles submitting the report form to be exported as a CSV
	var editorial_statistics_export_to_csv = function() {
		// Set the export format to CSV on the form
		$( '#editorial_statistics_output_format' ).val( 'csv' );
		
		// Open a popup window to submit the form
		//var w = window.open( 'about:blank', 'editorial_statistics_csv','toolbar=0, scrollbars=0, location=0, statusbar=0, menubar=0, resizable=0, width=200, height=200,left=0, top=0' );
		
		// Change the form target and submit
		//$( '#editorial_statistics_form' ).attr( 'target', 'editorial_statistics_csv' );
		$( '#editorial_statistics_form' ).submit();
		
		// Set the export format back to HTML for future submissions
		$( '#editorial_statistics_output_format' ).val( 'html' );
	}
	
	$( document ).ready( function() {
	
		// Initialize the datepickers
		$( '#editorial_statistics_start_date' ).datepicker();
		$( '#editorial_statistics_end_date' ).datepicker();
		
		// Initialize chosen for term selection
		$( '#editorial_statistics_terms' ).chosen();
		
		// Clear any error messages after fields are selected via chosen
		$( '#editorial_statistics_terms' ).chosen().change( function() {
			$( '#editorial_statistics_terms' ).siblings( '.error-message' ).html( '' );
		} );
		
		// Handle displaying the taxonomy selection when Term is selected as a column
		$( '#editorial_statistics_report_columns_term' ).on( 'click', function( event ) {
			editorial_statistics_show_field( $(this), $( '#editorial_statistics_terms_wrapper' ) );
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
				'editorial_statistics_terms[]': {
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
		
		// Handle CSV export
		$( '#editorial_statistics_export_to_csv' ).on( 'click', function( event ) {
			event.preventDefault();
			editorial_statistics_export_to_csv();
		} );
		
		// Determine whether or not to display the taxonomy selection field on load
		editorial_statistics_show_field( $( '#editorial_statistics_report_columns_term' ), $( '#editorial_statistics_terms_wrapper' ) );
		
	} );

} )( jQuery );