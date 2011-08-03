<?php
/**
 * mysqli database wrapper class
 * 
 * @author Eric Lakich
 * @version 1.0.1
 * @package Database Utilities
 * 
 * Copyright (c) 2010, Eric Lakich
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, 
 * are permitted provided that the following conditions are met:
 * 
 * * Redistributions of source code must retain the above copyright notice, 
 * this list of conditions and the following disclaimer.
 * * Redistributions in binary form must reproduce the above copyright notice, 
 * this list of conditions and the following disclaimer in the documentation 
 * and/or other materials provided with the distribution.
 * * Neither the name of the <ORGANIZATION> nor the names of its contributors 
 * may be used to endorse or promote products derived from this software without 
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE 
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR 
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF 
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS 
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 * 
 **/

//Define Exceptions
define('MYSQLI_ERR_ARG_PUSH_MISSING_VALUE','100 :: Missing argument value');
define('MYSQLI_ERR_ARG_PUSH_MISSING_TYPE','101 :: Missing argument type');
define('MYSQLI_ERR_INVALID_QUERY_STRING','102 :: Invalid query string');
define('MYSQLI_ERR_MISSING_QUERY','103 :: Missing query string');
#define('MYSQLI_ERR_INVALID_QUERY_STRING_TYPE','104 :: Invalid query type (SELECT, INSERT, UPDATE only)'); //UNUSED ERROR
define('MYSQLI_ERR_ARG_NUM_MISMATCH','105 :: The number of arguments pushed does not match the number of arguments in the query');
define('MYSQLI_ERR_FETCH_FAIL','106 :: Error fetching data from database.  Check your query.');

class mysqli_db {

	//Init Local Vars
	var $database_host;
	var $database_user;
	var $database_pass;
	var $database_name;
	
	protected $connection;
	protected $query_result;
	protected $QUERY;
	protected $QUERY_ARGS;
	protected $QUERY_TYPE;
	
	function __construct() {
		//do nothing
	}
	
	/*
	 * Open function
	 * 
	 * Establishes a connection to the mysql database
	 */
	public function open_mysqli_db() {
		
		$this->connection = new mysqli($this->database_host,
										$this->database_user,
										$this->database_pass,
										$this->database_name) or 
  		die("mysqli Connection Error: " . mysqli_error() );
	}
	
	/*
	 * Close function
	 * 
	 * closes out the database connection object
	 */
	public function close_mysqli_db() {
		$this->connection->close();
	}
		
	/*
	 * Debug Function
	 */
	private function debug($msg) {
		echo $msg;
	}
	
	/*
	 * Exception Function
	 * 
	 */
	private function except($err) {
		echo 'ERROR (mysqli_db class) :: ' . $err . '<br />';	
		exit();
	}
	
	/*
	 * Argument Check Function
	 * 
	 * Check to be sure that the number of arguments passed to the class
	 * match teh number of arguments in the query string
	 */
	private function query_arg_check() {
		//Set some variables
		$query_arg_count = 0;
		$arg_count = 0;
		$arg_match = False;	

		//Check number of arguments in the query
		if ($this->QUERY) {
			$query_arg_count = substr_count($this->QUERY, '?');
		}
		
		//Check number of arguments passes to the class
		if ($this->QUERY_ARGS) {
			foreach($this->QUERY_ARGS as $arg_count_value) {
				if ($arg_count_value) {
					$arg_count += 1;
				}
			}
		}
		
		//Match up argument counts
		if ( $query_arg_count == $arg_count ) {
			$arg_match = True;
		} else {
			$arg_match = False;
		}
		return $arg_match;
	}
	
	/*
	 * Query Set Functon
	 * 
	 * This function is used to set the query and parse the query type
	 */
	public function set_query($query_string) {
		
		$query_type = 'SELECT';
		$trimmed_query = '';
		
		if ($query_string) {
			
			$trimmed_query = trim($query_string); 
			
			$query_type = strtoupper(substr($trimmed_query,0,6) );
			
			$this->QUERY = $trimmed_query;	
			$this->QUERY_TYPE = $query_type;
					
		} else {
			
			$this->except(MYSQLI_ERR_MISSING_QUERY);
			
		}
		
		return True;
		
	}
	
	/*
	 * Argument Push Function
	 * 
	 * This function is used to push arguments into this class.  
	 * type is expected to be i,d,s,b (integer, double, string, blob)
	 * the 'name' is simply the name of the argument 
	 */
	public function push_argument($value,$type) {
		//Check to be sure argument types are valid
		if ($type == ''  || 
			($type != 'i' &&
			 $type != 'd' &&
			 $type != 's' &&
			 $type != 'b' ) ) { 
			$this->except( MYSQLI_ERR_ARG_PUSH_MISSING_TYPE );
		}

		//Add arg to query_arg_array
		$next_array_value = sizeof($this->QUERY_ARGS);
		$this->QUERY_ARGS[$next_array_value]['value'] = $value;
		$this->QUERY_ARGS[$next_array_value]['type'] = $type;
		
		return True;
	}
	
	/*
	 * PHP 5.3 convert bind values to ref values
	 */
	private function ref_values($arr){
	    if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
	    {
	        $refs = array();
	        foreach($arr as $key => $value)
	            $refs[$key] = &$arr[$key];
	        return $refs;
	    }
	    return $arr;
	}
	
	/*
	 * Fetch Data Function
	 * 
	 * This function fetches row/column data for select statements
	 */
	private function fetch_data($stmt) {
	
		$result = array();
	
		if ($stmt) {
	
			$metadata = $stmt->result_metadata();
			$fields = $metadata->fetch_fields();
	
			for (;;) {
				$pointers = array();
				$row = new stdClass();
				$pointers[] = $stmt;
	
				foreach ($fields as $field) {
					$fieldname = $field->name;
					$pointers[] = &$row->$fieldname;
				}
	
				call_user_func_array('mysqli_stmt_bind_result', $pointers);
	
				if ( !$stmt->fetch() ) { 
					break;
				}
					
				$result[] = $row;
			}
	
			$metadata->free();
			
		} else {
			$this->except( MYSQLI_ERR_FETCH_FAIL );
		}
	
		return $result;
	
	}
	
	/*
	 * Standard class object to array fix function
	 */
	private function std_class_object_to_array($stdclassobject) {
					
					$array = '';
					
					$_array = is_object($stdclassobject) ?
								get_object_vars($stdclassobject) :
								$stdclassobject;
	
					foreach ($_array as $key => $value) {
	
									$value = (is_array($value) || is_object($value)) ?
											 $this->std_class_object_to_array($value) :
											 $value;
	
							$array[$key] = $value;
					}
	
					return $array;
	}

	/*
	 * Get Results Functon
	 * 
	 * This function returns the result set based on query type.
	 * this function is also designed to do the heavy lifting...this is where 
	 * all of the checks are made to be sure the query will execute correct.
	 * The query is the executed and teh result set is returned 
	 */
	public function get_results() {
		//Begin actual functon
		$arg_check = $this->query_arg_check();
		
		//Check for matching number of arguments in query and arg array
		if (!$arg_check) {
			$this->except( MYSQLI_ERR_ARG_NUM_MISMATCH );
		} else {
			//Begin running the query
			if ($stmt = $this->connection->prepare($this->QUERY)) {
				
				$bind_values = array();
				$bind_types = '';
				$i=0;
		
				foreach ($this->QUERY_ARGS as $id=>$value) {
					$bind_values[$i] = $value['value'];
					$i++;
					$bind_types .= $value['type'];
				}
		
				call_user_func_array('mysqli_stmt_bind_param',
									  array_merge ( array($stmt,$bind_types),
									                $this->ref_values($bind_values)
									              )
									 );
									  
				$stmt->execute();
				
				//Get sql result based on query type
				switch ($this->QUERY_TYPE) {
					case 'SELECT';
						$result_class_object = $this->fetch_data($stmt);
						$result = $this->std_class_object_to_array($result_class_object);
						break;
					case 'INSERT';
						$result = $stmt->insert_id;
						break;
					case 'UPDATE';
						$result = $stmt->insert_id;
						break;
					default;
						$result = $stmt->num_rows;
						break;
				}
				
				$stmt->close();
				
				return $result;
			}
		}
	}
	
//END CLASS
}
?>