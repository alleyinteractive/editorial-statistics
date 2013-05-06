( function( $ ) {

$( document ).ready( function() {

	// Initialize the datepickers
	$( "#" + editorial_statistics.prefix + "start_date" ).datepicker();
	$( "#" + editorial_statistics.prefix + "end_date" ).datepicker();
	
	// Initialize chosen for term selection
	$( "#" + editorial_statistics.prefix + "terms" ).chosen();
	
} );

} )( jQuery );