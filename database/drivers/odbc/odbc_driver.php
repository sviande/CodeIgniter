<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		Rick Ellis
 * @copyright	Copyright (c) 2006, EllisLab, Inc.
 * @license		http://www.codeigniter.com/user_guide/license.html
 * @link		http://www.codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ODBC Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		Rick Ellis
 * @link		http://www.codeigniter.com/user_guide/database/
 */
class CI_DB_odbc_driver extends CI_DB {

	/**
	 * The syntax to count rows is slightly different across different
	 * database engines, so this string appears in each driver and is
	 * used for the count_all() and count_all_results() functions.
	 */
	var $_count_string = "SELECT COUNT(*) AS numrows ";
	var $_random_keyword = ' RND('.time().')'; // database specific random keyword

	/**
	 * Non-persistent database connection
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */	
	function db_connect()
	{
		return @odbc_connect($this->hostname, $this->username, $this->password);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Persistent database connection
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */	
	function db_pconnect()
	{
		return @odbc_pconnect($this->hostname, $this->username, $this->password);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Select the database
	 *
	 * @access	private called by the base class
	 * @return	resource
	 */	
	function db_select()
	{
		// Not needed for ODBC
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Version number query string
	 *
	 * @access	public
	 * @return	string
	 */
	function _version()
	{
		return "SELECT version() AS ver";
	}

	// --------------------------------------------------------------------

	/**
	 * Execute the query
	 *
	 * @access	private called by the base class
	 * @param	string	an SQL query
	 * @return	resource
	 */	
	function _execute($sql)
	{
		$sql = $this->_prep_query($sql);
		return @odbc_exec($this->conn_id, $sql);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Prep the query
	 *
	 * If needed, each database adapter can prep the query string
	 *
	 * @access	private called by execute()
	 * @param	string	an SQL query
	 * @return	string
	 */	
	function _prep_query($sql)
	{
		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Begin Transaction
	 *
	 * @access	public
	 * @return	bool		
	 */	
	function trans_begin($test_mode = FALSE)
	{
		if ( ! $this->trans_enabled)
		{
			return TRUE;
		}
		
		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			return TRUE;
		}

		// Reset the transaction failure flag.
		// If the $test_mode flag is set to TRUE transactions will be rolled back
		// even if the queries produce a successful result.
		$this->_trans_failure = ($test_mode === TRUE) ? TRUE : FALSE;

		return odbc_autocommit($this->conn_id, FALSE);
	}

	// --------------------------------------------------------------------

	/**
	 * Commit Transaction
	 *
	 * @access	public
	 * @return	bool		
	 */	
	function trans_commit()
	{
		if ( ! $this->trans_enabled)
		{
			return TRUE;
		}

		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			return TRUE;
		}

		$ret = odbc_commit($this->conn_id);
		odbc_autocommit($this->conn_id, TRUE);
		return $ret;
	}

	// --------------------------------------------------------------------

	/**
	 * Rollback Transaction
	 *
	 * @access	public
	 * @return	bool		
	 */	
	function trans_rollback()
	{
		if ( ! $this->trans_enabled)
		{
			return TRUE;
		}

		// When transactions are nested we only begin/commit/rollback the outermost ones
		if ($this->_trans_depth > 0)
		{
			return TRUE;
		}

		$ret = odbc_rollback($this->conn_id);
		odbc_autocommit($this->conn_id, TRUE);
		return $ret;
	}

	// --------------------------------------------------------------------

	/**
	 * Escape String
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function escape_str($str)	
	{
		// ODBC doesn't require escaping
		return $str;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Affected Rows
	 *
	 * @access	public
	 * @return	integer
	 */
	function affected_rows()
	{
		return @odbc_num_rows($this->conn_id);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Insert ID
	 *
	 * @access	public
	 * @return	integer
	 */
	function insert_id()
	{
		return @odbc_insert_id($this->conn_id);
	}

	// --------------------------------------------------------------------

	/**
	 * "Count All" query
	 *
	 * Generates a platform-specific query string that counts all records in
	 * the specified database
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function count_all($table = '')
	{
		if ($table == '')
			return '0';
	
		$query = $this->query($this->_count_string . "FROM `".$this->dbprefix.$table."`");
		
		if ($query->num_rows() == 0)
			return '0';

		$row = $query->row();
		return $row->numrows;
	}

	// --------------------------------------------------------------------

	/**
	 * Show table query
	 *
	 * Generates a platform-specific query string so that the table names can be fetched
	 *
	 * @access	private
	 * @return	string
	 */
	function _list_tables()
	{
		return "SHOW TABLES FROM `".$this->database."`";		
	}
	
	// --------------------------------------------------------------------

	/**
	 * Show column query
	 *
	 * Generates a platform-specific query string so that the column names can be fetched
	 *
	 * @access	public
	 * @param	string	the table name
	 * @return	string
	 */
	function _list_columns($table = '')
	{
		return "SHOW COLUMNS FROM ".$this->_escape_table($table);
	}

	// --------------------------------------------------------------------

	/**
	 * Field data query
	 *
	 * Generates a platform-specific query so that the column data can be retrieved
	 *
	 * @access	public
	 * @param	string	the table name
	 * @return	object
	 */
	function _field_data($table)
	{
		return "SELECT TOP 1 FROM ".$this->_escape_table($table);
	}

	// --------------------------------------------------------------------

	/**
	 * The error message string
	 *
	 * @access	private
	 * @return	string
	 */
	function _error_message()
	{
		return odbc_errormsg($this->conn_id);
	}
	
	// --------------------------------------------------------------------

	/**
	 * The error message number
	 *
	 * @access	private
	 * @return	integer
	 */
	function _error_number()
	{
		return odbc_error($this->conn_id);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Escape Table Name
	 *
	 * This function adds backticks if the table name has a period
	 * in it. Some DBs will get cranky unless periods are escaped
	 *
	 * @access	private
	 * @param	string	the table name
	 * @return	string
	 */
	function _escape_table($table)
	{
		if (stristr($table, '.'))
		{
			$table = preg_replace("/\./", "`.`", $table);
		}
		
		return $table;
	}
		
	// --------------------------------------------------------------------

	/**
	 * Insert statement
	 *
	 * Generates a platform-specific insert string from the supplied data
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the insert keys
	 * @param	array	the insert values
	 * @return	string
	 */
	function _insert($table, $keys, $values)
	{	
		return "INSERT INTO ".$this->_escape_table($table)." (".implode(', ', $keys).") VALUES (".implode(', ', $values).")";
	}
	
	// --------------------------------------------------------------------

	/**
	 * Update statement
	 *
	 * Generates a platform-specific update string from the supplied data
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the update data
	 * @param	array	the where clause
	 * @return	string
	 */
	function _update($table, $values, $where, $limit = FALSE)
	{
		foreach($values as $key => $val)
		{
			$valstr[] = $key." = ".$val;
		}
		
		$limit = (!$limit) ? '' : ' LIMIT '.$limit;
	
		return "UPDATE ".$this->_escape_table($table)." SET ".implode(', ', $valstr)." WHERE ".implode(" ", $where).$limit;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Delete statement
	 *
	 * Generates a platform-specific delete string from the supplied data
	 *
	 * @access	public
	 * @param	string	the table name
	 * @param	array	the where clause
	 * @return	string
	 */	
	function _delete($table, $where)
	{
		return "DELETE FROM ".$this->_escape_table($table)." WHERE ".implode(" ", $where);
	}

	// --------------------------------------------------------------------

	/**
	 * Limit string
	 *
	 * Generates a platform-specific LIMIT clause
	 *
	 * @access	public
	 * @param	string	the sql query string
	 * @param	integer	the number of rows to limit the query to
	 * @param	integer	the offset value
	 * @return	string
	 */
	function _limit($sql, $limit, $offset)
	{
		// Does ODBC doesn't use the LIMIT clause?
		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Close DB Connection
	 *
	 * @access	public
	 * @param	resource
	 * @return	void
	 */
	function _close($conn_id)
	{
		@odbc_close($conn_id);
	}

	
}


?>