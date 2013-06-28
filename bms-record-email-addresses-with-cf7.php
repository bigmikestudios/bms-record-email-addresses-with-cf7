<?php 
/*
	Plugin Name: BMS Record Email Addresses with CF7
	Plugin URI: http://bigmikestudios.com
	Description: Records email addresses from Contact Form 7 forms.
*/

// ==========================================================================

// On installation, create a table...
global $cf7_record_email_db_version;
$cf7_record_email_db_version = "1.0";

global $wpdb, $cf7_record_email_table_name;
$cf7_record_email_table_name = $wpdb->prefix . "cf7_recorded_emails";

function cf7_record_email_install() {
   global $wpdb;
   global $cf7_record_email_db_version;
   global $cf7_record_email_table_name;
   $table_name = $cf7_record_email_table_name;
      
   $sql = "CREATE TABLE $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  first_name tinytext NOT NULL,
  last_name tinytext NOT NULL,
  email tinytext NOT NULL,
  UNIQUE KEY id (id)
    );";

   require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
   dbDelta( $sql );
 
   add_option( "cf7_record_email_db_version", $cf7_record_email_db_version );
}

function cf7_record_email_install_data() {
   global $wpdb;
   global $cf7_record_email_db_version;
   global $cf7_record_email_table_name;
   $table_name = $cf7_record_email_table_name;
   
   $welcome_first_name = "Mr.";
   $welcome_last_name = "WordPress";
   $welcome_email_address = "mrwordpress@bigmikestudios.com";

   $rows_affected = $wpdb->insert( $table_name, array( 
   	'time' => current_time('mysql'), 
	'first_name' => $welcome_first_name, 
	'last_name' => $welcome_last_name, 
	'email' => $welcome_email_address ) );
}

register_activation_hook( __FILE__, 'cf7_record_email_install' );
register_activation_hook( __FILE__, 'cf7_record_email_install_data' );

// Add a hook to write info to the table when CF7 sends email...

add_action("wpcf7_before_send_mail", "wpcf7_record_email_before_send_mail");  
  
function wpcf7_record_email_before_send_mail(&$wpcf7_data) {  
    global $wpdb;
	global $cf7_record_email_db_version;
	global $cf7_record_email_table_name;
	$table_name = $cf7_record_email_table_name;
	
	// Here is the variable where the data are stored!  
	//var_dump($wpcf7_data);
	
	if (!is_array($wpcf7_data->posted_data['opt-in'])) $wpcf7_data->posted_data['opt-in'] = array($wpcf7_data->posted_data['opt-in']);
	if (in_array('Yes',$wpcf7_data->posted_data['opt-in'])) {
		

		$first_name = $wpcf7_data->posted_data['first-name'];
		$last_name = $wpcf7_data->posted_data['last-name'];
		$email = $wpcf7_data->posted_data['email'];
		
		$rows_affected = $wpdb->insert( $table_name, array( 
			'time' => current_time('mysql'), 
			'first_name' => $first_name, 
			'last_name' => $last_name, 
			'email' => $email ) );
		error_log($rows_affected );
	}
	
	// If you want to skip mailing the data, you can do it...  
	//$wpcf7_data->skip_mail = false;  
}  

// ==========================================================================

add_action('admin_menu', 'register_cf7_record_submenu_page');

function register_cf7_record_submenu_page() {
	add_submenu_page( 'tools.php', 'Contact Form 7 Harvested emails', 'Contact Form 7 Harvested emails', 'manage_options', 'cf7-harvested-emails', 'cf7_record_page_callback' ); 
}

function cf7_record_page_callback() {
	global $cf7_record_email_table_name, $wpdb;
	$table_name = $cf7_record_email_table_name;
	$one_month_ago = mktime(date("H"), date("i"), date("s"), date("m")-1, date("d"),   date("Y"));
	$one_month_ago = date("Y-m-d", $one_month_ago);
	$now = date("Y-m-d");
	
	$start_date = $one_month_ago;
	$end_date = $now;
	
	if (isset($_POST['start_date'])) $start_date = $_POST['start_date'];
	if (isset($_POST['end_date'])) $end_date = $_POST['end_date'];

	$start_time = $start_date." 00:00:00";
	$end_time = $end_date." 23:59:59";
	
	if (isset($_POST['purge'])) {
		if ($_POST['purge'] == "Delete") {
			$wpdb->delete( $table_name, array("1" => "1"));
		}
	}
	
	$myrows = $wpdb->get_results( "SELECT first_name, last_name, email, time FROM ".$table_name. " WHERE time >= '$start_time' AND time <= '$end_time' ORDER BY time DESC" );
	
?>
<h2>Contact Form 7 Harvested Emails</h2>
<p>Here is a list of harvested contact information, and the date it was recorded.</p>
<p><strong>Be responsible with this information.</strong> At the time their information was collected, users opted in, so purge this list regularly to make sure you aren't spamming people who may have since opted out.</p>

<hr />
<h3>Results</h3>
<table class="cf7_record_table">
<tr><th>First Name</th><th>Last Name</th><th>Email</th><th>Date Entered</th><th>Formatted Email Address</th></tr>
<?php foreach($myrows as $row):?>
<tr>
<td><?php echo $row->first_name; ?></td>
<td><?php echo $row->last_name; ?></td>
<td><?php echo $row->email; ?></td>
<td><?php echo $row->time; ?></td>
<td><?php echo $row->first_name." ".$row->last_name." &lt;".$row->email."&gt;";?></td></tr>
<?php endforeach ?>
</table>
<style type="text/css">
.cf7_record_table th, .cf7_record_table td { text-align: left;  padding: .5em 2em .5em 0;}
</style>
<hr />

<form action="?page=cf7-harvested-emails" method="post">
<h3>Filter dates</h3>
<p>Start Date: <input name="start_date" type="date" value="<?php echo $start_date; ?>"/> End Date: <input name="end_date" type="date"value="<?php echo $end_date; ?>" /> <input type="submit" /></p>
<hr />
<h3>Purge table</h3>
<p><input type="submit" name="purge" value="Delete" onclick="javascript: return(confirm('You are about to delete all records in the table. Are you sure?'));" /></p>
</form>

<hr />
<h3>Programming notes</h3>
<p>For this plugin to work, fields in your contact form must be defined for first_name, last_name, email, and opt-in. For the entry to be added to the db, the opt-in field value must be "Yes".</p>
<?php
}