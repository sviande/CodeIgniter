<?php
namespace CI\Database\cubrid;

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		Esen Sagynov
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 2.0.2
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CUBRID Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		Esen Sagynov
 * @link		http://codeigniter.com/user_guide/database/
 */
class Driver extends \CI\database\ActiveRecord
{
    // Default CUBRID Broker port. Will be used unless user
    // explicitly specifies another one.
    const DEFAULT_PORT = 33000;

    public $dbdriver = 'cubrid';

    // The character used for escaping - no need in CUBRID
    public $escape_char = '';

    // clause and character used for LIKE escape sequences - not used in CUBRID
    public $like_escape_str = '';
    public $like_escape_chr = '';

    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the countAll() and countAllResults() functions.
     */
    public $count_string = 'SELECT COUNT(*) AS ';
    public $random_keyword = ' RAND()'; // database specific random keyword

    /**
     * Non-persistent database connection
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function dbConnect()
    {
        // If no port is defined by the user, use the default value
        if ($this->port == '') {
            $this->port = self::DEFAULT_PORT;
        }

        $conn = cubrid_connect($this->hostname, $this->port, $this->database, $this->username, $this->password);

        if ($conn) {
            // Check if a user wants to run queries in dry, i.e. run the
            // queries but not commit them.
            if (isset($this->auto_commit) && ! $this->auto_commit) {
                cubrid_set_autocommit($conn, CUBRID_AUTOCOMMIT_FALSE);
            } else {
                cubrid_set_autocommit($conn, CUBRID_AUTOCOMMIT_TRUE);
                $this->auto_commit = TRUE;
            }
        }

        return $conn;
    }

    // --------------------------------------------------------------------

    /**
     * Persistent database connection
     * In CUBRID persistent DB connection is supported natively in CUBRID
     * engine which can be configured in the CUBRID Broker configuration
     * file by setting the CCI_PCONNECT parameter to ON. In that case, all
     * connections established between the client application and the
     * server will become persistent. This is calling the same
     * @cubrid_connect function will establish persisten connection
     * considering that the CCI_PCONNECT is ON.
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function dbPConnect()
    {
        return $this->dbConnect();
    }

    // --------------------------------------------------------------------

    /**
     * Reconnect
     *
     * Keep / reestablish the db connection if no queries have been
     * sent for a length of time exceeding the server's idle timeout
     *
     * @access	public
     * @return	void
     */
    public function reconnect()
    {
        if (cubrid_ping($this->conn_id) === FALSE) {
            $this->conn_id = FALSE;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Select the database
     *
     * @access	private called by the base class
     * @return	resource
     */
    public function dbSelect()
    {
        // In CUBRID there is no need to select a database as the database
        // is chosen at the connection time.
        // So, to determine if the database is "selected", all we have to
        // do is ping the server and return that value.
        return cubrid_ping($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Set client character set
     *
     * @access	public
     * @param	string
     * @param	string
     * @return	resource
     */
    public function dbSetCharset($charset, $collation)
    {
        // In CUBRID, there is no need to set charset or collation.
        // This is why returning true will allow the application continue
        // its normal process.
        return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * Version number query string
     *
     * @access	public
     * @return	string
     */
    public function versionStatement()
    {
        // To obtain the CUBRID Server version, no need to run the SQL query.
        // CUBRID PHP API provides a function to determin this value.
        // This is why we also need to add 'cubrid' value to the list of
        // $driver_version_exceptions array in DB_driver class in
        // version() function.
        return cubrid_get_server_info($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * execute the query
     *
     * @access	private called by the base class
     * @param	string	an SQL query
     * @return	resource
     */
    public function execute($sql)
    {
        $sql = $this->_prep_query($sql);
        return @cubrid_query($sql, $this->conn_id);
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
    public function _prep_query($sql)
    {
        // No need to prepare
        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Begin Transaction
     *
     * @access	public
     * @return	bool
     */
    public function transBegin($test_mode = FALSE)
    {
        if ( ! $this->trans_enabled) {
            return TRUE;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            return TRUE;
        }

        // Reset the transaction failure flag.
        // If the $test_mode flag is set to TRUE transactions will be rolled back
        // even if the queries produce a successful result.
        $this->_trans_failure = ($test_mode === TRUE) ? TRUE : FALSE;

        if (cubrid_get_autocommit($this->conn_id)) {
            cubrid_set_autocommit($this->conn_id, CUBRID_AUTOCOMMIT_FALSE);
        }

        return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * Commit Transaction
     *
     * @access	public
     * @return	bool
     */
    public function transCommit()
    {
        if ( ! $this->trans_enabled) {
            return TRUE;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            return TRUE;
        }

        cubrid_commit($this->conn_id);

        if ($this->auto_commit && ! cubrid_get_autocommit($this->conn_id)) {
            cubrid_set_autocommit($this->conn_id, CUBRID_AUTOCOMMIT_TRUE);
        }

        return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * Rollback Transaction
     *
     * @access	public
     * @return	bool
     */
    public function transRollback()
    {
        if ( ! $this->trans_enabled) {
            return TRUE;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->_trans_depth > 0) {
            return TRUE;
        }

        cubrid_rollback($this->conn_id);

        if ($this->auto_commit && ! cubrid_get_autocommit($this->conn_id)) {
            cubrid_set_autocommit($this->conn_id, CUBRID_AUTOCOMMIT_TRUE);
        }

        return TRUE;
    }

    // --------------------------------------------------------------------

    /**
     * Escape String
     *
     * @access	public
     * @param	string
     * @param	bool	whether or not the string will be used in a LIKE condition
     * @return	string
     */
    public function escapeStr($str, $like = FALSE)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escapeStr($val, $like);
            }

            return $str;
        }

        if (function_exists('cubrid_real_escape_string') AND is_resource($this->conn_id)) {
            $str = cubrid_real_escape_string($str, $this->conn_id);
        } else {
            $str = addslashes($str);
        }

        // escape LIKE condition wildcards
        if ($like === TRUE) {
            $str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Affected Rows
     *
     * @access	public
     * @return	integer
     */
    public function affectedRows()
    {
        return @cubrid_affected_rows($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Insert ID
     *
     * @access	public
     * @return	integer
     */
    public function insertId()
    {
        return @cubrid_insert_id($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified table
     *
     * @access	public
     * @param	string
     * @return	string
     */
    public function countAll($table = '')
    {
        if ($table == '') {
            return 0;
        }

        $query = $this->query($this->count_string . $this->protectIdentifiers('numrows') . " FROM " . $this->protectIdentifiers($table, TRUE, NULL, FALSE));

        if ($query->num_rows() == 0) {
            return 0;
        }

        $row = $query->row();
        $this->resetSelect();
        return (int) $row->numrows;
    }

    // --------------------------------------------------------------------

    /**
     * List table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access	private
     * @param	boolean
     * @return	string
     */
    public function listTablesStatement($prefix_limit = FALSE)
    {
        $sql = "SHOW TABLES";

        if ($prefix_limit !== FALSE AND $this->dbprefix != '') {
            $sql .= " LIKE '".$this->escapeLikeStr($this->dbprefix)."%'";
        }

        return $sql;
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
    public function listColumnsStatement($table = '')
    {
        return "SHOW COLUMNS FROM ".$this->protectIdentifiers($table, TRUE, NULL, FALSE);
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
    public function fieldDataStatement($table)
    {
        return "SELECT * FROM ".$table." LIMIT 1";
    }

    // --------------------------------------------------------------------

    /**
     * The error message string
     *
     * @access	private
     * @return	string
     */
    public function errorMessage()
    {
        return cubrid_error($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * The error message number
     *
     * @access	private
     * @return	integer
     */
    public function errorNumber()
    {
        return cubrid_errno($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access	private
     * @param	string
     * @return	string
     */
    public function escapeIdentifiers($item)
    {
        if ($this->escape_char == '') {
            return $item;
        }

        foreach ($this->reserved_identifiers as $id) {
            if (strpos($item, '.'.$id) !== FALSE) {
                $str = $this->escape_char. str_replace('.', $this->escape_char.'.', $item);

                // remove duplicates if the user already included the escape
                return preg_replace('/['.$this->escape_char.']+/', $this->escape_char, $str);
            }
        }

        if (strpos($item, '.') !== FALSE) {
            $str = $this->escape_char.str_replace('.', $this->escape_char.'.'.$this->escape_char, $item).$this->escape_char;
        } else {
            $str = $this->escape_char.$item.$this->escape_char;
        }

        // remove duplicates if the user already included the escape
        return preg_replace('/['.$this->escape_char.']+/', $this->escape_char, $str);
    }

    // --------------------------------------------------------------------

    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access	public
     * @param	type
     * @return	type
     */
    public function fromTablesStatement($tables)
    {
        if ( ! is_array($tables)) {
            $tables = array($tables);
        }

        return '('.implode(', ', $tables).')';
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
    public function insertStatement($table, $keys, $values)
    {
        return "INSERT INTO ".$table." (\"".implode('", "', $keys)."\") VALUES (".implode(', ', $values).")";
    }

    // --------------------------------------------------------------------


    /**
     * Replace statement
     *
     * Generates a platform-specific replace string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    public function replaceStatement($table, $keys, $values)
    {
        return "REPLACE INTO ".$table." (\"".implode('", "', $keys)."\") VALUES (".implode(', ', $values).")";
    }

    // --------------------------------------------------------------------

    /**
     * Insert_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the insert keys
     * @param	array	the insert values
     * @return	string
     */
    public function insertBatchStatement($table, $keys, $values)
    {
        return "INSERT INTO ".$table." (\"".implode('", "', $keys)."\") VALUES ".implode(', ', $values);
    }

    // --------------------------------------------------------------------

    protected function updateStatement($table, $values, $where, $orderby = array(), $limit = FALSE)
    {
        foreach ($values as $key => $val) {
            $valstr[] = sprintf('"%s" = %s', $key, $val);
        }

        $limit = ( ! $limit) ? '' : ' LIMIT '.$limit;

        $orderby = (count($orderby) >= 1)?' ORDER BY '.implode(", ", $orderby):'';

        $sql = "UPDATE ".$table." SET ".implode(', ', $valstr);

        $sql .= ($where != '' AND count($where) >=1) ? " WHERE ".implode(" ", $where) : '';

        $sql .= $orderby.$limit;

        return $sql;
    }

    // --------------------------------------------------------------------


    /**
     * Update_Batch statement
     *
     * Generates a platform-specific batch update string from the supplied data
     *
     * @access	public
     * @param	string	the table name
     * @param	array	the update data
     * @param	array	the where clause
     * @return	string
     */
    public function updateBatchStatement($table, $values, $index, $where = NULL)
    {
        $ids = array();
        $where = ($where != '' AND count($where) >=1) ? implode(" ", $where).' AND ' : '';

        foreach ($values as $key => $val) {
            $ids[] = $val[$index];

            foreach (array_keys($val) as $field) {
                if ($field != $index) {
                    $final[$field][] = 'WHEN '.$index.' = '.$val[$index].' THEN '.$val[$field];
                }
            }
        }

        $sql = "UPDATE ".$table." SET ";
        $cases = '';

        foreach ($final as $k => $v) {
            $cases .= $k.' = CASE '."\n";
            foreach ($v as $row) {
                $cases .= $row."\n";
            }

            $cases .= 'ELSE '.$k.' END, ';
        }

        $sql .= substr($cases, 0, -2);

        $sql .= ' WHERE '.$where.$index.' IN ('.implode(',', $ids).')';

        return $sql;
    }

    // --------------------------------------------------------------------


    /**
     * Truncate statement
     *
     * Generates a platform-specific truncate string from the supplied data
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @access	public
     * @param	string	the table name
     * @return	string
     */
    public function truncateStatement($table)
    {
        return "TRUNCATE ".$table;
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
     * @param	string	the limit clause
     * @return	string
     */
    public function deleteStatement($table, $where = array(), $like = array(), $limit = FALSE)
    {
        $conditions = '';

        if (count($where) > 0 OR count($like) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);

            if (count($where) > 0 && count($like) > 0) {
                $conditions .= " AND ";
            }
            $conditions .= implode("\n", $like);
        }

        $limit = ( ! $limit) ? '' : ' LIMIT '.$limit;

        return "DELETE FROM ".$table.$conditions.$limit;
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
    public function limitStatement($sql, $limit, $offset)
    {
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ", ";
        }

        return $sql."LIMIT ".$offset.$limit;
    }

    // --------------------------------------------------------------------

    /**
     * Close DB Connection
     *
     * @access	public
     * @param	resource
     * @return	void
     */
    public function dbClose($conn_id)
    {
        @cubrid_close($conn_id);
    }

}


/* End of file cubrid_driver.php */
/* Location: ./system/database/drivers/cubrid/cubrid_driver.php */
