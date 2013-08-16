<?php
/*
 Plugin Name: Editorial Statistics
 Description: Allows editors to generate reports in HTML or CSV format for posts published per author, content type, and/or taxonomy term within a specified date range. Compatible with Co-Authors Plus.
 Author: Alley Interactive (Bradford Campeau-Laurion)
 Version: 0.1
 Author URI: http://alleyinteractive.com
 */

require_once( dirname( __FILE__ ) . '/php/class-plugin-dependency.php' );

class Editorial_Statistics {
	private static $__instance = NULL;

	/** @type string Prefix to use for all plugin fields and settings */
	private $prefix = 'editorial_statistics_';
	
	/** @type string i18n name */
	private $i18n = 'editorial_statistics';
	
	/** @type string Plugin name */
	private $plugin_name = 'Editorial Statistics'; 

	/** @type array Errors generated by the plugin */
	public $errors = array();
	
	/** @type array Available report columns */
	public $report_columns = array( 'author', 'content_type', 'term' );
	
	/** @type array Available report columns */
	public $filtered_post_types = array( 'attachment', 'guest-author' );
	
	/** @type string Default date format to use for reports and date fields */
	public $default_date_format = 'm/d/Y';

	/** @type string Base url to alley stats API host */
	public $alleystats_api_url = 'http://api.alleystats.com/enn-edstats.php';
	
	/** @type array Predefined date ranges */
	public $date_ranges = array();
	
	/** @type string Screen ID */
	private $screen_id = 'tools_page_editorial-statistics'; 
	
	/** @type object Dependency check on Co-Authors Plus **/
	private $coauthors_plus;
	

	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	function __construct() {

	}


	/**
	 * Init function
	 *
	 * @access public
	 * @static
	 * @return void
	 */
	public static function init() {
		self::instance()->prepare();
	}


	/**
	 * Return singleton instance for this class
	 *
	 * @access public
	 * @static
	 * @return object Singleton instance for this class
	 */
	public static function instance() {
		if ( self::$__instance == NULL )
			self::$__instance = new Editorial_Statistics;
		return self::$__instance;
	}


	/**
	 * Prepare settings, variables and hooks
	 *
	 * @access public
	 * @return void
	 */
	public function prepare() {
		add_action( 'init', array( &$this, 'setup_plugin' ) );
		add_action( 'wp_loaded', array( &$this, 'check_csv_export' ) );
	}


	/**
	 * Initialize menus and scripts
	 *
	 * @access public
	 * @return void
	 */
	public function setup_plugin() {
		// Add action hooks to display the report interface and to handle exports
		add_action( 'admin_menu', array( &$this, 'register_management_page' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		
		// Add the date ranges
		$this->date_ranges = array( 
			array( 
				'name' => 'Yesterday', 
				'start_date' => date( $this->default_date_format, strtotime( 'yesterday' ) ),
				'end_date' => date( $this->default_date_format, strtotime( 'yesterday' ) )
			),
			array(
				'name' => 'Week to Date',
				'start_date' => date( $this->default_date_format, strtotime( 'last monday' ) ),
				'end_date' => date( $this->default_date_format )
			),
			array(
				'name' => 'This Month',
				'start_date' => date( $this->default_date_format, strtotime( 'first day of this month' ) ),
				'end_date' => date( $this->default_date_format, strtotime( 'last day of this month' ) )
			),
			array(
				'name' => 'Last Month',
				'start_date' => date( $this->default_date_format, strtotime( 'first day of last month' ) ),
				'end_date' => date( $this->default_date_format, strtotime( 'last day of last month' ) )
			) 
		);
		
		// Add the dependency check used later to check if Co-Authors Plus is active
		$this->coauthors_plus = new Plugin_Dependency( $this->plugin_name, 'Co-Authors Plus', 'http://wordpress.org/extend/plugins/co-authors-plus/' );
	}


	/**
	 * Enqueue required scripts
	 *
	 * @access public
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue the scripts for the Editorial Statistics report screen
		$screen = get_current_screen();

		if ( $screen->id == $this->screen_id ) {
			// Enqueue the plugin script
			wp_enqueue_script( $this->prefix . 'js', plugin_dir_url( __FILE__ ) . 'js/editorial-statistics.js', false, '1.0', true );
			
			// Add chosen.js for the taxonomy selection field
			wp_enqueue_script( 'chosen', plugin_dir_url( __FILE__ ) . 'js/chosen/chosen.jquery.min.js', false, '1.0', true );
			wp_enqueue_style( 'chosen_css', plugin_dir_url( __FILE__ ) . 'js/chosen/chosen.css', false, '1.0' );
			
			// Add form validation
			wp_enqueue_script( 'jquery-validate', plugin_dir_url( __FILE__ ) . 'js/jquery.validate.min.js', false, '1.0', true );
		
			// Enqueue the plugin styles
			wp_enqueue_style( $this->prefix . 'css', plugin_dir_url( __FILE__ ) . 'css/editorial-statistics.css', false, '1.0' );
			wp_enqueue_style( 'jquery.ui.theme', plugin_dir_url( __FILE__ ) . 'css/jquery-ui/jquery-ui-1.10.3.custom.css', false, '1.10.3' );
		
			// Include the jquery datepicker for the report date range
			wp_enqueue_script( 'jquery-ui-datepicker' );
		}
	}
	
	
	/**
	 * Create the tools menu item for running editorial statistics reports
	 *
	 * @access public
	 * @return void
	 */
	public function register_management_page() {
		add_management_page( __( $this->plugin_name ), __( $this->plugin_name ), 'edit_others_posts', 'editorial-statistics', array( &$this, 'management_page' ) );
	}
	
	
	/**
	 * Output the management page for running editorial statistics reports
	 *
	 * @access public
	 * @return void
	 */
	public function management_page() {
		?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2><?php _e( $this->plugin_name, $this->i18n ) ?></h2>

				<form id="editorial_statistics_form" method="post" action="">
					<input type="hidden" name="<?php echo $this->prefix ?>output_format" id="<?php echo $this->prefix ?>output_format" value="<?php echo ( isset( $_POST[$this->prefix . 'output_format'] ) ) ? esc_attr( $_POST[$this->prefix . 'output_format'] ) : 'html' ?>" />
					<h3><?php _e( 'Report Settings', $this->i18n ) ?></h3>
					<?php wp_nonce_field( $this->prefix . 'nonce' ) ?>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label for="<?php echo $this->prefix ?>start_date">
									<div>
										<b><?php _e( 'Start Date', $this->i18n ) ?></b>
									</div>
									<div><?php _e( 'Set the start date for the report.', $this->i18n ) ?></div>
								</label>
							</th>
							<td>
								<input type="text" name="<?php echo $this->prefix ?>start_date" id="<?php echo $this->prefix ?>start_date" value="<?php echo ( isset( $_POST[$this->prefix . 'start_date'] ) ) ? esc_attr( $_POST[$this->prefix . 'start_date'] ) : '' ?>" />
								<div class="error-message"></div>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="<?php echo $this->prefix ?>end_date">
									<div>
										<b><?php _e( 'End Date', $this->i18n ) ?></b>
									</div>
									<div><?php _e( 'Set the end date for the report.', $this->i18n ) ?></div>
								</label>
							</th>
							<td>
								<input type="text" name="<?php echo $this->prefix ?>end_date" id="<?php echo $this->prefix ?>end_date" value="<?php echo ( isset( $_POST[$this->prefix . 'end_date'] ) ) ? esc_attr( $_POST[$this->prefix . 'end_date'] ) : '' ?>" />
								<div class="error-message"></div>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="<?php echo $this->prefix ?>predefined_date_ranges">
									<div>
										<b><?php _e( 'Predefined Date Ranges', $this->i18n ) ?></b>
									</div>
									<div><?php _e( 'Choose a predefined date range to set the start and end dates for the report.', $this->i18n ) ?></div>
								</label>
							</th>
							<td>
								<?php 
									$date_range_output = array();
									foreach( $this->date_ranges as $date_range ) {
										$date_range_output[] = sprintf(
											'<a href="#" class="editorial-statistics-date-range" data-start-date="%s" data-end-date="%s">%s</a>',
											$date_range['start_date'],
											$date_range['end_date'],
											__( $date_range['name'], $this->i18n )
										);
									}
									echo implode( '&nbsp;&nbsp;|&nbsp;&nbsp;', $date_range_output );
								?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="<?php echo $this->prefix ?>report_columns">
									<div>
										<b><?php _e( 'Report Columns', $this->i18n ) ?></b>
									</div>
									<div><?php _e( 'Choose the columns to display in the report (at least one is required).', $this->i18n ) ?></div>
								</label>
							</th>
							<td>
								<?php foreach( $this->report_columns as $report_column ): ?>
									<?php 
										echo sprintf(
											'<input type="checkbox" value="%s" name="%sreport_columns[]" id="%sreport_columns_%s" class="editorial-statistics-report-column" %s />',
											esc_attr( $report_column ),
											esc_attr( $this->prefix ),
											esc_attr( $this->prefix ),
											esc_attr( $report_column ),
											( isset( $_POST[$this->prefix . 'report_columns'] ) && in_array( $report_column, $_POST[$this->prefix . 'report_columns'] ) ) ? ' checked="checked"' : ''
										);
									?>
									<?php _e( ucwords( str_replace( '_', ' ', $report_column ) ), $this->i18n ) ?><br />
								<?php endforeach; ?>
								<div class="error-message"></div>
							</td>
						</tr>
						<tr valign="top" id="<?php echo $this->prefix ?>terms_wrapper" class="editorial-statistics-filter" >
							<th scope="row">
								<label for="<?php echo $this->prefix ?>terms">
									<div>
										<b><?php _e( 'Choose Taxonomies' ) ?></b>
									</div>
									<div><?php _e( 'Choose which taxonomies should be included in the term column (at least one is required).', $this->i18n ) ?></div>
								</label>
							</th>
							<td>
								<?php 
									echo sprintf(
										'<select multiple="multiple" class="chzn-select" name="%s" id="%s" data-placeholder="%s">%s</select>',
										$this->prefix . 'terms[]',
										$this->prefix . 'terms',
										__( 'Select Taxonomies' ),
										$this->get_taxonomy_options()
									);
								?>
								<div class="error-message"></div>
							</td>
						</tr>
					</table>
	
					<p class="submit">
						<?php submit_button( __( 'Create Report' ), 'primary', $this->prefix . 'submit', false ); ?>
						<input type="button" name="editorial_statistics_reset" id="editorial_statistics_reset" class="button delete" value="<?php _e( 'Reset Options', $this->i18n ) ?>" />
					</p>
	
				</form>
				<?php
					// If the form was submitted, display the report in HTML format.
					$this->create_report();
				?>
			</div>
		<?php
	}
	
	
	/**
	 * Builds the list of options for the taxonomy selection field
	 * 
	 * @access private
	 * @return string
	 */
	private function get_taxonomy_options() {
		// Initialize the container for the taxonomy options list
		$taxonomy_options = '';

		// Get the list of available taxonomies.
		// Just return an empty string if this blank for some reason.
		$taxonomies = $this->get_taxonomies();
		if ( empty( $taxonomies ) || !is_array( $taxonomies ) )
			return $taxonomy_options;
			
		foreach( $taxonomies as $taxonomy ) {
			$taxonomy_options .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $taxonomy->name ),
				( isset( $_POST[$this->prefix . 'terms'] ) && in_array( $taxonomy->name, $_POST[$this->prefix . 'terms'] ) ) ? ' selected="selected"' : '',
				esc_html( $taxonomy->label )
			);
		}
		
		return $taxonomy_options;
	}


	/**
	 * Get the list of taxonomies for use in filtering reports
	 *
	 * @access private
	 * @return array
	 */
	private function get_taxonomies() {
		// Only return taxonomies that are shown in the admin interface.
		// Otherwise, the list could be confusing to editors or provide invalid options.
		return get_taxonomies(
			array( 
				'public' => true,
				'show_ui' => true
			),
			'objects'
		);
	}
	
	
	/**
	 * Get the list of post types for use in filtering reports
	 *
	 * @access private
	 * @return array
	 */
	private function get_post_types() {
		// Only return post types that are shown in the admin interface.
		// Otherwise, the list could be confusing to editors or provide invalid options.
		$post_types = get_post_types(
			array( 
				'public' => true,
				'show_ui' => true
			),
			'names'
		);
		
		// Also remove unwanted post types from this list
		$post_types = array_filter( $post_types, array( &$this, 'filter_post_types' ) );
		
		// Return the post types to be used for the report
		return $post_types;
	}
	
	
	/**
	 * Filters unwanted post types for the report
	 *
	 * @access private
	 * @param string $post_type
	 * @return array
	 */
	private function filter_post_types( $post_type ) {
		// Apply filters to allow the list of filtered post types to be editable
		$post_types_to_filter = apply_filters( $this->prefix . 'filtered_post_types', $this->filtered_post_types );
		
		return ! in_array( $post_type, $post_types_to_filter );
	}

	
	/**
	 * Create the output for the editorial statistics report
	 *
	 * @access private
	 * @param string $mode
	 * @return void
	 */
	private function create_report( $mode = 'html' ) {
		// Check if the report form was submitted. If not, just return.
		if ( ! isset( $_POST['editorial_statistics_output_format'] ) || empty( $_POST['editorial_statistics_output_format'] ) )
			return;

		// Capability check
		if ( ! current_user_can( 'edit_others_posts' ) )
			wp_die( __( 'You do not have access to perform this action' ) );

		// Form nonce check
		check_admin_referer( $this->prefix . 'nonce' );

		// If the output format does not match the mode, exit
		// This is important to prevent HTML reports from being output during the init action reserved for CSV reports
		if ( $_POST[$this->prefix . 'output_format'] != $mode )
			return;

		// Query for all posts for public post types in the specified date range
		add_filter( 'posts_where', array( &$this, 'report_date_filter' ) );
		$args = array(
			'post_type' => $this->get_post_types(),
			'posts_per_page' => -1
		);
		$posts_query = new WP_Query( $args );
		$posts = $posts_query->get_posts();		
		remove_filter( 'posts_where', array( &$this, 'report_date_filter' ) );

		if ( count( $posts ) > 0 && isset( $_POST[$this->prefix . 'report_columns'] ) ) { 
			// Create an array to hold the final data
			$report_data = array();

			// Fetch viewcounts
			$viewcounts = $this->fetch_viewcounts( $posts, 'gothamschools', intval( strtotime( $_POST[$this->prefix . 'start_date'] ) ), intval( strtotime( $_POST[$this->prefix . 'end_date'] ) ) );
			print_r($viewcounts);
			// Now we will iterate over each post. 
			// The available report columns are author, content type, and tag in that order. 
			// Based on which were selected, we will group counts for each into a multidimensional array
			// that will later serve as the final output of the report. 
			foreach( $posts as $post ) {
				// Build the array keys that will be used to classify and count this post
				// Report granularity should always be displayed as author, then content type and then term for the specified taxonomies
				$keys = array();
			
				if ( in_array( 'author', $_POST[$this->prefix . 'report_columns'] ) ) {
					$authors = array();
					if ( $this->coauthors_plus->verify() ) {
						foreach( get_coauthors( $post->ID ) as $coauthor ) {
							$authors[] = $coauthor->display_name;
						}
					} else { 
						$authors[] = $post->post_author;
					}

					$keys[] = $authors;
				}
				
				if ( in_array( 'content_type', $_POST[$this->prefix . 'report_columns'] ) ) {
					// Use an array here to be consistent with authors and terms and simplify the recursive function that adds totals
					$keys[] = array( get_post_type_object( $post->post_type )->labels->singular_name );
				}
				
				if ( in_array( 'term', $_POST[$this->prefix . 'report_columns'] ) ) {
					$taxonomy_terms = array();
					foreach( wp_get_post_terms( $post->ID, $_POST[$this->prefix . 'terms'] ) as $term ) {
						$taxonomy_terms[] = $term->name;
					}
					
					// If there are no terms, just use 'None'
					if ( empty( $taxonomy_terms ) )
						$taxonomy_terms[] = __( 'None', $this->i18n );
					
					$keys[] = $taxonomy_terms;
				}
				print_r($viewcounts);
				print $post->ID . ' ';
				foreach ( $viewcouunts as $k => $v ) {
					print "c: " . $k . ' - ' . $post->ID;
				}
				if ( array_key_exists( $post->ID, $viewcounts ) ) {
					print "y ";
					$viewcount = $viewcounts[$post->ID];
				}
				else {
					print "n ";
					$viewcount = 0;
				}
				$viewcount = $viewcounts[$post->ID];
				print $viewcount;
				// Add this story to the totals for the appropriate rows in the final report
				$report_data = $this->add_report_totals( $report_data, $keys, $viewcount );
			}

			print "<pre>" . print_r($report_data, true) . "</pre>";
			
			// Sort the data for the report
			$this->sort_report_data( $report_data );
			
			// Output the data
			$this->output_report_data( $report_data, $_POST[$this->prefix . 'output_format'], $_POST[$this->prefix . 'report_columns'] );
				
		} else if ( ( count( $posts ) == 0 || ! isset( $_POST[$this->prefix . 'report_columns'] ) ) && $_POST[$this->prefix . 'output_format'] == 'html' ) { ?>
			<h4><?php _e( 'No results were returned for the current report settings.', $this->i18n ) ?></h4>
		<?php }
	}
	
	
	/**
	 * Adds a date filter for the report
	 *
	 * @access public
	 * @param string $where
	 * @return string
	 */
	public function report_date_filter( $where = '' ) {
		// Ensure the date parameters are set. Otherwise invalidate the query.
		if( isset( $_POST[$this->prefix . 'start_date'] ) && isset( $_POST[$this->prefix . 'end_date'] ) )
			$where .= " AND post_date >= '" . date( 'Y-m-d', intval( strtotime( $_POST[$this->prefix . 'start_date'] ) ) ) . "' AND post_date < '" . date( 'Y-m-d', intval( strtotime( $_POST[$this->prefix . 'end_date'] ) ) + ( 60*60*24 ) ) . "'";
		else
			$where .= " AND 1=2";
			
		return $where;
	}
	
	
	/**
	 * Recursive function to add to the totals for the final report
	 * Adds one to the lowest level of the indexes provided
	 *
	 * @access private
	 * @param array $report_data
	 * @param array $keys
	 * @param int $viewcount
	 * @return string
	 */
	private function add_report_totals( $report_data, $keys, $viewcount ) {
		// Get the keys used for this level and shift them off the array of keys
		$lvl_keys = array_shift( $keys );
		
		// Iterate through the keys for this level of the report
		foreach( $lvl_keys as $lvl_key ) {
			// If the keys array is now empty, we have reached the lowest level and add one to the key
			// Otherwise, recurse with the trimmed keys array and set this index equal to the result
			if ( empty( $keys ) ) {
				if ( empty( $report_data[$lvl_key] ) ) $report_data[$lvl_key] = array( 'article_count' => 0, 'view_count' => 0 );
				$report_data[$lvl_key]['article_count']++;
				$report_data[$lvl_key]['view_count'] += $viewcount;
			} else {
				// If this key does not yet exist, initialize it as an empty array
				if ( ! array_key_exists( $lvl_key, $report_data ) )
					$report_data[$lvl_key] = array();
				
				// Recurse with the newly trimmed keys array to get down to the final level to add totals
				$report_data[$lvl_key] = $this->add_report_totals( $report_data[$lvl_key], $keys, $viewcount );
			}
		}
		
		// Return the final report data
		return $report_data;
	}
	
	
	/**
	 * Outputs the final data for the report
	 * Uses a simple HTML table or CSV format
	 *
	 * @access private
	 * @param array $report_data
	 * @param string $report_format
	 * @param string $report_columns
	 * @return string
	 */
	private function output_report_data( $report_data, $report_format = 'html', $report_columns ) {
		// Generate the data for the report
		$this->generate_report_data( $output_data, $report_data, $_POST[$this->prefix . 'output_format'] );
		
		// Output based on the format specified
		if( $report_format == 'csv' ) {

			// Set the filename for the report
			$filename = 'report.csv';
			
			// Output the headers required for CSV export
			header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
			header( 'Content-Description: File Transfer' );
			header( 'Content-type: text/csv' );
			header( "Content-Disposition: attachment; filename={$filename}" );
			header( 'Expires: 0' );
			header( 'Pragma: public' );
			
			// Open the file handle for streaming output
			$fh = @fopen( 'php://output', 'w' );
			
			// Write the headers
			$report_columns = array_map( array( &$this, 'format_report_column' ), $report_columns );
			$report_columns[] = __( 'Total Stories', $this->i18n );
			$report_columns[] = __( 'Total Views', $this->i18n );
			$header_row = sprintf(
				"\"%s\"\n",
				implode( '","', $report_columns )
			);
			fwrite( $fh, $header_row );
			
			// Write the data
			fwrite( $fh, $output_data );
			
			// Close the file handle
			fclose( $fh );
			
			// Exit at this point because we don't want to display anything further in the popup window
			exit;	
		} else {
		?>
			<h3><?php _e( 'Report', $this->i18n ) ?></h3>
			<div><a href="#" id="<?php echo esc_attr( $this->prefix ); ?>export_to_csv"><?php _e( 'Export to CSV', $this->i18n ) ?></a></div>
			<table id="report_table">
				<tr>
					<?php	
						foreach( $report_columns as $report_column ) {
							echo sprintf(
								'<td class="header">%s</td>',
								$this->format_report_column( $report_column )
							);
						}
					?>
					<td class="header"><?php _e( 'Total Stories', $this->i18n ) ?></td>
					<td class="header"><?php _e( 'Total Views', $this->i18n ) ?></td>
				</tr>
				<?php echo $output_data ?>
			</table>
		<?php
		}
	}
	
	
	/**
	 * Generates the data for the report in either HTML or CSV format
	 *
	 * @access private
	 * @param string $output_data
	 * @param array $report_data
	 * @param string $report_format
	 * @param array $row_values
	 * @return string
	 */
	private function generate_report_data( &$output_data, $report_data, $report_format = 'html', $row_values = array() ) {
		// Determine if we are still building a row of data or if it is ready to be output
		if ( is_array( $report_data ) && ! array_key_exists( 'article_count', $report_data ) && ! array_key_exists( 'view_count', $report_data ) ) {
			// This is still an array of data so get the keys for the current level
			// Add it to the row values as the next column of data to be output and pass its value to this function recursively
			$keys = array_keys( $report_data );
			
			foreach( $keys as $key ) {
				// Add this key as a column in the report
				$row_values[] = $key;
				
				// Recursively call this function until we reach the total (i.e. final column)
				$this->generate_report_data( $output_data, $report_data[$key], $report_format, $row_values );
				
				// Pop this column off the output data before we iterate to the next one
				array_pop( $row_values );
			}
		} else {
			// If report data is not an array, we've reached the lowest level of the report data and should output the row
			// Set the row start, end and separators based on the output format
			switch( $report_format ) {
				case 'csv':
					$row_start = '"';
					$row_end = "\"\n";
					$separator = '","';
					break;
				case 'html':
				default:
					$row_start = '<tr><td>';
					$row_end = '</td></tr>';
					$separator = '</td><td>';
					break;
			}
			
			// Start the row
			$output_data .= $row_start;

			// Output the row data after adding the final column, which is the total
			$row_values[] = esc_html( $report_data['article_count'] );
			$row_values[] = esc_html( $report_data['view_count'] );
			$output_data .= implode( $separator, $row_values );
			
			// End the row
			$output_data .= $row_end;
		}
	}
	
	
	/**
	 * Recursive function to sort the data before displaying it
	 *
	 * @access private
	 * @param array $report_data
	 * @return string
	 */
	private function sort_report_data( &$report_data ) {
		foreach( $report_data as $column ) {
			if ( is_array( $column ) )
				$this->sort_report_data( $column );
		}
		ksort( $report_data );
	}

	/**
	 * Fetch viewcounts for a series of posts
	 *
	 * @access private
	 * @param array $posts
	 * @return array
	 */
	private function fetch_viewcounts( $posts, $client, $from_ts, $to_ts ) {
		$post_ids = array();
		foreach( $posts as $post ) {
			$post_ids[] = $post->ID;
		}
		$url = $this->alleystats_api_url . '?';
		$args = array(
			'action=viewcount',
			'from=' . $from_ts,
			'to=' . $to_ts,
			'client=' . $client,
			'event_type=view_article',
			'ids=' . implode( ',', $post_ids )
		);
		$url .= implode( '&', $args );
		$result = wp_remote_get( $url );
		$counts = json_decode( $result['body'] );
		return (array)$counts;
	}
	
	
	/**
	 * Format a report column type for output in a report
	 *
	 * @access private
	 * @param string $report_column
	 * @return void
	 */
	public function format_report_column( $report_column ) {
		return __( ucwords( str_replace( '_', ' ', $report_column ) ), $this->i18n );
	}
	
	
	/**
	 * Check if a CSV export was requested
	 *
	 * @access public
	 * @return void
	 */
	public function check_csv_export() {
		// Check if the report form was submitted for CSV export
		// If so, intercept it here and output the required headers to stream a CSV to the browser
		$this->create_report( 'csv' );
	}
	

	/**
	 * Log an error
	 *
	 * @param string $message 
	 * @return bool false
	 */
	private function error( $message ) {
		$this->errors[] = $message;
		return false;
	}


	/**
	 * Display any errors on the site
	 *
	 * @return void
	 */
	public function display_errors() {
		if ( count( $this->errors ) ) :
		?>
		<div id="message" class="error">
			<p>
				<?php echo _n( 'There was an issue retrieving your user account: ', 'There were issues retrieving your user account: ', count( $this->errors ), $this->i18n ) ?>
				<br /> &bull; <?php echo implode( "\n\t\t\t\t<br /> &bull; ", $this->errors ) ?>
			</p>
		</div>
		<?php
		endif;
	}

}

Editorial_Statistics::init();