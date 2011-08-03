<?php

/*DATABASE CONNECTION FUNCTION
 * This function uses the included mysqli class to perform queries 
 * no arguments
 */

// ***************************************
// include the mysqli class
// ***************************************
require('class.mysqli.php');

// ***************************************
// connection function
// ***************************************
function connect() {
	// create the class object
	$db = new mysqli_db;
	
	// configure the connection credentials 
	$db->database_host = "localhost";
	$db->database_user = "username";
	$db->database_pass = "password";
	$db->database_name = "database"; 
	
	// open the connection
	$db->open_mysqli_db();
	
	// return the connection result 
	return $db;
}

// ***************************************
// sample query function
// ***************************************
function sql_sample_function($input_id, $input_name) {
	// define the query
	$query = "SELECT * FROM sometable WHERE id = ? AND name = ?";
	
	// create a connection instance
	$db = connect();
	
	// set the query
	$db->set_query($query);
	
	// push arguments to the query
	$db->push_argument($input_id,'i'); // i=integer d=double s=string b=blob
	$db->push_argument($input_name,'s'); // i=integer d=double s=string b=blob
	
	// get the query result
	$result = $db->get_results(); 
	
	// close the connection instance
	$db->close_mysqli_db(); 
	
	// return the query result as a name=>value array
	return $result;
	
}

// ***************************************
// get the results from the query
// ***************************************
$query_results = sql_sample_function(1,"John Doe");

if (is_aray($query_results)) {
	foreach ($query_results as $row=>$column) {
		echo "The column value for the 'id' column is: " . $column['id'];
		echo "The column value for the 'name' column is: " . $column['name'];
		echo "The column value for the 'address' column is: " . $column['address'];
	}	
}

?>