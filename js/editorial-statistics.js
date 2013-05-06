( function( $ ) {

	// Handles showing/hiding additional fields required for certain columns in the report
	var editorial_statistics_show_field = function( $checkbox, $target ) {
		if( $checkbox.attr( "checked" ) == "checked" )
			$target.show();
		else
			$target.hide();
	}
	
	// Handles setting a predefined date range
	var editorial_statistics_set_date_range = function( $start, $end, range ) {
		var current_date, start_date, end_date;
		
		// Set the current date to use in date calculations
		current_date = new Date();
		
		switch( range ) {
			case 'yesterday':
				console.log( current_date.getTime() );
				start_date = end_date = new Date( current_date.getTime() - 60*60*24 );
				break;
			case 'week_to_date':
			
				break;
			case 'this_month':
			
				break;
			case 'last_month':
			
				break;
			default:
				// If for some reason this receives an invalid range, it will just do nothing but this should never be possible
				return;
				break; 
		}
		
		$start.val( editorial_statistics_format_date( start_date.getMonth(), start_date.getDay(), start_date.getYear() ) );
		$end.val( editorial_statistics_format_date( end_date.getMonth(), end_date.getDay(), end_date.getYear() ) );
	}
	
	// Handles formatting a date
	var editorial_statistics_format_date = function( month, day, year ) {
		return month + "/" + day + "/" + year;
	}
	
	$( document ).ready( function() {
	
		// Initialize the datepickers
		$( "#" + editorial_statistics.prefix + "start_date" ).datepicker();
		$( "#" + editorial_statistics.prefix + "end_date" ).datepicker();
		
		// Initialize chosen for term selection
		$( "#" + editorial_statistics.prefix + "terms" ).chosen();
		
		// Handle displaying the taxonomy selection when Term is selected as a column
		$( "#editorial_statistics_report_columns_term" ).on( "click", function( event ) {
			editorial_statistics_show_field( $(this), $( "#" + editorial_statistics.prefix + "terms_wrapper" ) );
		} );
		
		// Handle setting a predefined date range when a link is clicked
		$( ".editorial-statistics-date-range" ).on( "click", function( event ) {
			editorial_statistics_set_date_range( $( "#" + editorial_statistics.prefix + "start_date" ), $( "#" + editorial_statistics.prefix + "end_date" ), $(this).data( "range" ) );
		} );
		
		// Determine whether or not to display the taxonomy selection field on load
		editorial_statistics_show_field( $(this), $( "#" + editorial_statistics.prefix + "terms_wrapper" ) );
		
	} );

} )( jQuery );