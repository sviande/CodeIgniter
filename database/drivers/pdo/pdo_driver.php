<?php
namespace CI\Database\Pdo;

    /**
     * CodeIgniter
     *
     * An open source application development framework for PHP 5.1.6 or newer
     *
     * @package    CodeIgniter
     * @copyright  Copyright (c) 2008 - 2011, EllisLab, Inc.
     * @license    http://codeigniter.com/user_guide/license.html
     * @author    EllisLab Dev Team
     * @link    http://codeigniter.com
     * @since    Version 2.1.2
     * @filesource
     */

// ------------------------------------------------------------------------

/**
 * PDO Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the active record
 * class is being used or not.
 *
 * @package    CodeIgniter
 * @subpackage  Drivers
 * @category  Database
 * @author    EllisLab Dev Team
 * @link    http://codeigniter.com/user_guide/database/
 *
 * @property \PDO $conn_id
 */
class Driver extends \CI\Database\ActiveRecord
{
    public $dbdriver = 'pdo';

    // the character used to excape - not necessary for PDO
    public $escape_char = '';
    public $like_escape_str;
    public $like_escape_chr;


    /**
     * The syntax to count rows is slightly different across different
     * database engines, so this string appears in each driver and is
     * used for the countAll() and countAllResults() functions.
     */
    public $count_string = "SELECT COUNT(*) AS ";
    public $random_keyword;

    public $options = array();

    public function __construct($params)
    {
        parent::__construct($params);

        // clause and character used for LIKE escape sequences
        if (strpos($this->hostname, 'mysql') !== false) {
            $this->_like_escape_str = '';
            $this->_like_escape_chr = '';

            //Prior to this version, the charset can't be set in the dsn
            if (\CI\Core\is_php('5.3.6')) {
                $this->hostname .= ";charset={$this->char_set}";
            }

            //Set the charset with the connection options
            $this->options['PDO::MYSQL_ATTR_INIT_COMMAND'] = "SET NAMES {$this->char_set}";
        } elseif (strpos($this->hostname, 'odbc') !== false) {
            $this->_like_escape_str = " {escape '%s'} ";
            $this->_like_escape_chr = '!';
        } else {
            $this->_like_escape_str = " ESCAPE '%s' ";
            $this->_like_escape_chr = '!';
        }

        empty($this->database) || $this->hostname .= ';dbname=' . $this->database;

        $this->trans_enabled = false;

        $this->_random_keyword = ' RND(' . time() . ')'; // database specific random keyword
    }

    /**
     * Non-persistent database connection
     *
     * @access  private called by the base class
     * @return  \PDO
     */
    public function dbConnect()
    {
        $this->options['PDO::ATTR_ERRMODE'] = \PDO::ERRMODE_SILENT;

        return new \PDO($this->hostname, $this->username, $this->password, $this->options);
    }

    // --------------------------------------------------------------------

    /**
     * Persistent database connection
     *
     * @access  private called by the base class
     * @return  \PDO
     */
    public function dbPconnect()
    {
        $this->options['PDO::ATTR_ERRMODE']    = \PDO::ERRMODE_SILENT;
        $this->options['PDO::ATTR_PERSISTENT'] = true;

        return new \PDO($this->hostname, $this->username, $this->password, $this->options);
    }

    // --------------------------------------------------------------------

    public function reconnect()
    {
        if ($this->db->db_debug) {
            return $this->db->display_error('db_unsuported_feature');
        }
        return false;
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
        // Not needed for PDO
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Set client character set
     *
     * @access  public
     * @param  string
     * @param  string
     * @return  resource
     */
    public function dbSetCharset($charset, $collation)
    {
        // @todo - add support if needed
        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Version number query string
     *
     * @access  public
     * @return  string
     */
    public function version()
    {
        return $this->conn_id->getAttribute(\PDO::ATTR_CLIENT_VERSION);
    }

    // --------------------------------------------------------------------

    public function execute($sql)
    {
        $sql       = $this->prepQuery($sql);
        $result_id = $this->conn_id->prepare($sql);
        $result_id->execute();

        if (is_object($result_id)) {
            if (is_numeric(stripos($sql, 'SELECT'))) {
                $this->affect_rows = count($result_id->fetchAll());
                $result_id->execute();
            } else {
                $this->affect_rows = $result_id->rowCount();
            }
        } else {
            $this->affect_rows = 0;
        }

        return $result_id;
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
        return $sql;
    }

    // --------------------------------------------------------------------

    protected function transBegin($test_mode = false)
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
        $this->trans_failure = (bool)($test_mode === true);

        return $this->conn_id->beginTransaction();
    }

    // --------------------------------------------------------------------

    /**
     * Commit Transaction
     *
     * @access  public
     * @return  bool
     */
    protected function transCommit()
    {
        if (!$this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 0) {
            return true;
        }

        $ret = $this->conn_id->commit();
        return $ret;
    }

    // --------------------------------------------------------------------

    /**
     * Rollback Transaction
     *
     * @access  public
     * @return  bool
     */
    protected function transRollback()
    {
        if (!$this->trans_enabled) {
            return true;
        }

        // When transactions are nested we only begin/commit/rollback the outermost ones
        if ($this->trans_depth > 0) {
            return true;
        }

        $ret = $this->conn_id->rollBack();
        return $ret;
    }

    // --------------------------------------------------------------------

    /**
     * Escape String
     *
     * @access  public
     * @param  string $str
     * @param  bool $like whether or not the string will be used in a LIKE condition
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

        //Escape the string
        $str = $this->conn_id->quote($str);

        //If there are duplicated quotes, trim them away
        if (strpos($str, "'") === 0) {
            $str = substr($str, 1, -1);
        }

        // escape LIKE condition wildcards
        if ($like === true) {
            $str = str_replace(
                array(
                    '%',
                    '_',
                    $this->_like_escape_chr
                ),
                array(
                    $this->_like_escape_chr . '%',
                    $this->_like_escape_chr . '_',
                    $this->_like_escape_chr . $this->_like_escape_chr
                ),
                $str
            );
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
        return $this->affect_rows;
    }

    // --------------------------------------------------------------------

    /**
     * Insert ID
     *
     * @access  public
     * @param string $name
     * @return  integer
     */
    public function insertId($name = null)
    {
        //Convenience method for postgres insertid
        $sql ='';
        if (strpos($this->hostname, 'pgsql') !== false) {
            $v = $this->version();

            $table = func_num_args() > 0 ? func_get_arg(0) : null;

            if ($table == null && $v >= '8.1') {
                $sql = 'SELECT LASTVAL() as ins_id';
            }
            $query = $this->query($sql);
            $row   = $query->row();
            return $row->ins_id;
        } else {
            return $this->conn_id->lastInsertId($name);
        }
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
     * Show table query
     *
     * Generates a platform-specific query string so that the table names can be fetched
     *
     * @access  private
     * @param  boolean
     * @return  string
     */
    public function listTablesStatement($prefix_limit = false)
    {
        $sql = "SHOW TABLES FROM `" . $this->database . "`";

        if ($prefix_limit !== false && $this->dbprefix != '') {
            return false; // not currently supported
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    public function listColumnsStatement($table = '')
    {
        return "SHOW COLUMNS FROM " . $table;
    }

    // --------------------------------------------------------------------

    public function fieldDataStatement($table)
    {
        return "SELECT TOP 1 FROM " . $table;
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
        $error_array = $this->conn_id->errorInfo();
        return $error_array[2];
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
        return $this->conn_id->errorCode();
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

    public function fromTablesStatement($tables)
    {
        if (!is_array($tables)) {
            $tables = array($tables);
        }

        return (count($tables) == 1) ? $tables[0] : '(' . implode(', ', $tables) . ')';
    }

    // --------------------------------------------------------------------

    public function insertStatement($table, $keys, $values)
    {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
    }

    // --------------------------------------------------------------------

    public function insertBatchStatement($table, $keys, $values)
    {
        return "INSERT INTO " . $table . " (" . implode(', ', $keys) . ") VALUES " . implode(', ', $values);
    }

    // --------------------------------------------------------------------

    public function updateStatement($table, $values, $where, $orderby = array(), $limit = false)
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
        $final = array();
        $ids   = array();
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
        return $this->deleteStatement($table);
    }

    // --------------------------------------------------------------------

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

    public function limitStatement($sql, $limit, $offset)
    {
        if (strpos($this->hostname, 'cubrid') !== false || strpos($this->hostname, 'sqlite') !== false) {
            if ($offset == 0) {
                $offset = '';
            } else {
                $offset .= ", ";
            }

            return $sql . "LIMIT " . $offset . $limit;
        } else {
            $sql .= "LIMIT " . $limit;

            if ($offset > 0) {
                $sql .= " OFFSET " . $offset;
            }

            return $sql;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Close DB Connection
     *
     * @access  public
     * @param  resource
     * @return  void
     */
    public function close($conn_id)
    {
        $this->conn_id = null;
    }
}



/* End of file pdo_driver.php */
/* Location: ./system/database/drivers/pdo/pdo_driver.php */
