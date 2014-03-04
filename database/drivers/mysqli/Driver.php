<?php
namespace CI\Database\Mysqli;

use \CI\Database\ActiveRecord;

    /**
     * CodeIgniter
     *
     * An open source application development framework for PHP 5.1.6 or newer
     *
     * @package    CodeIgniter
     * @author    ExpressionEngine Dev Team
     * @copyright  Copyright (c) 2008 - 2011, EllisLab, Inc.
     * @license    http://codeigniter.com/user_guide/license.html
     * @link    http://codeigniter.com
     * @since    Version 1.0
     * @filesource
     */

// ------------------------------------------------------------------------


/**
 * MySQLi Database Adapter Class - MySQLi only works with PHP 5
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package    CodeIgniter
 * @subpackage  Drivers
 * @category  Database
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/database/
 *
 * @property \mysqli $conn_id
 */
class Driver extends ActiveRecord
{
    public $dbdriver = 'mysqli';

    // The character used for escaping
    protected $escape_char = '`';

    // clause and character used for LIKE escape sequences - not used in MySQL
    public $like_escape_str = '';
    public $like_escape_chr = '';

    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the countAll() and countAllResults() functions.
     */
    public $count_string = "SELECT COUNT(*) AS ";
    public $random_keyword = ' RAND()'; // database specific random keyword

    /**
     * Whether to use the MySQL "delete hack" which allows the number
     * of affected rows to be shown. Uses a preg_replace when enabled,
     * adding a bit more processing to all queries.
     */
    public $delete_hack = true;

    // whether SET NAMES must be used to set the character set
    public $use_set_names;

    // --------------------------------------------------------------------

    /**
     * Non-persistent database connection
     *
     * @access  private called by the base class
     * @return  resource
     */
    protected function dbConnect()
    {
        if ($this->port != '') {
            return @mysqli_connect($this->hostname, $this->username, $this->password, $this->database, $this->port);
        } else {
            return @mysqli_connect($this->hostname, $this->username, $this->password, $this->database);
        }

    }

    // --------------------------------------------------------------------

    /**
     * Persistent database connection
     *
     * @access  private called by the base class
     * @return  resource
     */
    protected function dbPConnect()
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
     * @access  public
     * @return  void
     */
    public function reconnect()
    {
        if (mysqli_ping($this->conn_id) === false) {
            $this->conn_id = false;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Select the database
     *
     * @access  private called by the base class
     * @return  resource
     */
    public function dbSelect()
    {
        return @mysqli_select_db($this->conn_id, $this->database);
    }

    // --------------------------------------------------------------------

    /**
     * Set client character set
     *
     * @access  private
     * @param  string
     * @param  string
     * @return  resource
     */
    public function dbSetCharset($charset, $collation)
    {
        if (!isset($this->use_set_names)) {
            // mysqli_set_charset() requires MySQL >= 5.0.7, use SET NAMES as fallback
            $this->use_set_names = (version_compare(
                mysqli_get_server_info($this->conn_id),
                '5.0.7',
                '>='
            )) ? false : true;
        }

        if ($this->use_set_names === true) {
            return @mysqli_query(
                $this->conn_id,
                "SET NAMES '" . $this->escapeStr($charset) . "' COLLATE '" . $this->escapeStr($collation) . "'"
            );
        } else {
            return @mysqli_set_charset($this->conn_id, $charset);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Version number query string
     *
     * @access  public
     * @return  string
     */
    protected function versionStatement()
    {
        return "SELECT version() AS ver";
    }

    // --------------------------------------------------------------------

    /**
     * execute the query
     *
     * @access  private called by the base class
     * @param  string $sql an SQL query
     * @return  resource
     */
    public function execute($sql)
    {
        $sql    = $this->prepQuery($sql);
        $result = @mysqli_query($this->conn_id, $sql);
        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Prep the query
     *
     * If needed, each database adapter can prep the query string
     *
     * @access  private called by execute()
     * @param  string $sql an SQL query
     * @return  string
     */
    protected function prepQuery($sql)
    {
        // "DELETE FROM TABLE" returns 0 affected rows This hack modifies
        // the query so that it returns the number of affected rows
        if ($this->delete_hack === true) {
            if (preg_match('/^\s*DELETE\s+FROM\s+(\S+)\s*$/i', $sql)) {
                $sql = preg_replace("/^\s*DELETE\s+FROM\s+(\S+)\s*$/", "DELETE FROM \\1 WHERE 1=1", $sql);
            }
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Begin Transaction
     *
     * @access  public
     * @param bool $test_mode
     * @return bool
     */
    public function transBegin($test_mode = false)
    {
        if (!$this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 0) {
            return true;
        }

        // Reset the transaction failure flag.
        // If the $test_mode flag is set to TRUE transactions will be rolled back
        // even if the queries produce a successful result.
        $this->trans_failure = ($test_mode === true) ? true : false;

        $this->simpleQuery('SET AUTOCOMMIT=0');
        $this->simpleQuery('START TRANSACTION'); // can also be BEGIN or BEGIN WORK
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Commit Transaction
     *
     * @access  public
     * @return  bool
     */
    public function transCommit()
    {
        if (!$this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 0) {
            return true;
        }

        $this->simpleQuery('COMMIT');
        $this->simpleQuery('SET AUTOCOMMIT=1');
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Rollback Transaction
     *
     * @access  public
     * @return  bool
     */
    public function transRollback()
    {
        if (!$this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 0) {
            return true;
        }

        $this->simpleQuery('ROLLBACK');
        $this->simpleQuery('SET AUTOCOMMIT=1');
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Escape String
     *
     * @access  public
     * @param  string
     * @param  bool  whether or not the string will be used in a LIKE condition
     * @return  string
     */
    public function escapeStr($str, $like = false)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escapeStr($val, $like);
            }

            return $str;
        }

        if (is_object($this->conn_id)) {
            $str = mysqli_real_escape_string($this->conn_id, $str);
        } else {
            $str = addslashes($str);
        }

        // escape LIKE condition wildcards
        if ($like === true) {
            $str = str_replace(array('%', '_'), array('\\%', '\\_'), $str);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Affected Rows
     *
     * @access  public
     * @return  integer
     */
    public function affectedRows()
    {
        return @mysqli_affected_rows($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Insert ID
     *
     * @access  public
     * @return  integer
     */
    public function insertId()
    {
        return @mysqli_insert_id($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * "Count All" query
     *
     * Generates a platform-specific query string that counts all records in
     * the specified database
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function countAll($table = '')
    {
        if ($table == '') {
            return 0;
        }

        $query = $this->query(
            $this->count_string . $this->protectIdentifiers('numrows') . " FROM " . $this->protectIdentifiers(
                $table,
                true,
                null,
                false
            )
        );

        if ($query->num_rows() == 0) {
            return 0;
        }

        $row = $query->row();
        $this->resetSelect();
        return (int)$row->numrows;
    }

    // --------------------------------------------------------------------

    /**
     * List table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access  private
     * @param  boolean
     * @return  string
     */
    public function listTablesStatement($prefix_limit = false)
    {
        $sql = "SHOW TABLES FROM " . $this->escape_char . $this->database . $this->escape_char;

        if ($prefix_limit !== false && $this->dbprefix != '') {
            $sql .= " LIKE '" . $this->escapeLikeStr($this->dbprefix) . "%'";
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Show column query
     *
     * Generates a platform-specific query string so that the column names can be fetched
     *
     * @access  public
     * @param  string $table the table name
     * @return  string
     */
    public function listColumnsStatement($table = '')
    {
        return "SHOW COLUMNS FROM " . $this->protectIdentifiers($table, true, null, false);
    }

    // --------------------------------------------------------------------

    /**
     * Field data query
     *
     * Generates a platform-specific query so that the column data can be retrieved
     *
     * @access  public
     * @param  string $table the table name
     * @return  object
     */
    public function fieldDataStatement($table)
    {
        return "DESCRIBE " . $table;
    }

    // --------------------------------------------------------------------

    /**
     * The error message string
     *
     * @access  private
     * @return  string
     */
    public function errorMessage()
    {
        return mysqli_error($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * The error message number
     *
     * @access  private
     * @return  integer
     */
    public function errorNumber()
    {
        return mysqli_errno($this->conn_id);
    }

    // --------------------------------------------------------------------

    /**
     * Escape the SQL Identifiers
     *
     * This function escapes column and table names
     *
     * @access  private
     * @param  string
     * @return  string
     */
    public function escapeIdentifiers($item)
    {
        if ($this->escape_char == '') {
            return $item;
        }

        foreach ($this->reserved_identifiers as $id) {
            if (strpos($item, '.' . $id) !== false) {
                $str = $this->escape_char . str_replace('.', $this->escape_char . '.', $item);

                // remove duplicates if the user already included the escape
                return preg_replace('/[' . $this->escape_char . ']+/', $this->escape_char, $str);
            }
        }

        if (strpos($item, '.') !== false) {
            $str = $this->escape_char .
                str_replace(
                    '.',
                    $this->escape_char . '.' . $this->escape_char,
                    $item
                ) . $this->escape_char;
        } else {
            $str = $this->escape_char . $item . $this->escape_char;
        }

        // remove duplicates if the user already included the escape
        return preg_replace('/[' . $this->escape_char . ']+/', $this->escape_char, $str);
    }

    // --------------------------------------------------------------------

    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access public
     * @param array|string $tables
     * @return string
     */
    protected function fromTablesStatement($tables)
    {
        if (!is_array($tables)) {
            $tables = array($tables);
        }

        return '(' . implode(', ', $tables) . ')';
    }

    // --------------------------------------------------------------------

    /**
     * Insert statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access  public
     * @param  string $table the table name
     * @param  array  $keys the insert keys
     * @param  array  $values the insert values
     * @return  string
     */
    public function insertStatement($table, $keys, $values)
    {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }

    // --------------------------------------------------------------------

    /**
     * Insert_batch statement
     *
     * Generates a platform-specific insert string from the supplied data
     *
     * @access  public
     * @param  string $table the table name
     * @param  array  $keys the insert keys
     * @param  array  $values the insert values
     * @return  string
     */
    public function insertBatchStatement($table, $keys, $values)
    {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES " . implode(', ', $values);
    }

    // --------------------------------------------------------------------

    public function replaceStatement($table, $keys, $values)
    {
        return "REPLACE INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }

    // --------------------------------------------------------------------

    protected function updateStatement($table, $values, $where, $orderby = array(), $limit = false)
    {
        $valstr = array();
        foreach ($values as $key => $val) {
            $valstr[] = $key . " = " . $val;
        }

        $limit = (!$limit) ? '' : ' LIMIT ' . $limit;

        $orderby = (count($orderby) >= 1) ? ' ORDER BY ' . implode(", ", $orderby) : '';

        $sql = "UPDATE " . $table . " SET " . implode(', ', $valstr);

        $sql .= ($where != '' && count($where) >= 1) ? " WHERE " . implode(" ", $where) : '';

        $sql .= $orderby . $limit;

        return $sql;
    }

    // --------------------------------------------------------------------

    public function updateBatchStatement($table, $values, $index, $where = null)
    {
        $ids   = array();
        $final = array();
        $where = ($where != '' && count($where) >= 1) ? implode(" ", $where) . ' AND ' : '';

        foreach ($values as $val) {
            $ids[] = $val[$index];

            foreach (array_keys($val) as $field) {
                if ($field != $index) {
                    $final[$field][] = 'WHEN ' . $index . ' = ' . $val[$index] . ' THEN ' . $val[$field];
                }
            }
        }

        $sql   = "UPDATE " . $table . " SET ";
        $cases = '';

        foreach ($final as $k => $v) {
            $cases .= $k . ' = CASE ' . "\n";
            foreach ($v as $row) {
                $cases .= $row . "\n";
            }

            $cases .= 'ELSE ' . $k . ' END, ';
        }

        $sql .= substr($cases, 0, -2);

        $sql .= ' WHERE ' . $where . $index . ' IN (' . implode(',', $ids) . ')';

        return $sql;
    }

    // --------------------------------------------------------------------


    public function truncateStatement($table)
    {
        return "TRUNCATE " . $table;
    }

    public function deleteStatement($table, $where = array(), $like = array(), $limit = false)
    {
        $conditions = '';

        if (count($where) > 0 || count($like) > 0) {
            $conditions = "\nWHERE ";
            $conditions .= implode("\n", $this->ar_where);

            if (count($where) > 0 && count($like) > 0) {
                $conditions .= " AND ";
            }
            $conditions .= implode("\n", $like);
        }

        $limit = (!$limit) ? '' : ' LIMIT ' . $limit;

        return "DELETE FROM " . $table . $conditions . $limit;
    }

    // --------------------------------------------------------------------


    protected function limitStatement($sql, $limit, $offset)
    {
        $sql .= "LIMIT " . $limit;

        if ($offset > 0) {
            $sql .= " OFFSET " . $offset;
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Close DB Connection
     *
     * @access  public
     * @param  resource
     * @return  void
     */
    public function dbClose($conn_id)
    {
        @mysqli_close($conn_id);
    }
}


/* End of file Driver.php */
/* Location: ./system/database/drivers/mysqli/Driver.php */
