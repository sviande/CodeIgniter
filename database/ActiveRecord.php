<?php
namespace CI\Database;

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
 * Active Record Class
 *
 * This is the platform-independent base Active Record implementation class.
 *
 * @package    CodeIgniter
 * @subpackage  Drivers
 * @category  Database
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/database/
 */
abstract class ActiveRecord extends Driver
{

    protected $ar_select = array();
    protected $ar_distinct = null;
    protected $ar_from = array();
    protected $ar_join = array();
    protected $ar_where = array();
    protected $ar_like = array();
    protected $ar_groupby = array();
    protected $ar_having = array();
    protected $ar_keys = array();
    protected $ar_limit = null;
    protected $ar_offset = null;
    protected $ar_order = null;
    protected $ar_orderby = array();
    protected $ar_set = array();
    protected $ar_wherein = array();
    protected $ar_aliased_tables = array();
    protected $ar_store_array = array();

    // Active Record Caching variables
    protected $ar_caching = false;
    protected $ar_cache_exists = array();
    protected $ar_cache_select = array();
    protected $ar_cache_from = array();
    protected $ar_cache_join = array();
    protected $ar_cache_where = array();
    protected $ar_cache_like = array();
    protected $ar_cache_groupby = array();
    protected $ar_cache_having = array();
    protected $ar_cache_orderby = array();
    protected $ar_cache_set = array();

    protected $ar_no_escape = array();
    protected $ar_cache_no_escape = array();

    protected $like_escape_str = '';
    protected $like_escape_chr = '';
    protected $random_keyword;
    protected $count_string;

    /**
     * Truncate statement
     *
     * Generates a platform-specific truncate string from the supplied data
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @access public
     * @param string $table the table name
     * @return string
     */
    abstract public function truncateStatement($table);

    /**
     * From Tables
     *
     * This function implicitly groups FROM tables so there is no confusion
     * about operator precedence in harmony with SQL standards
     *
     * @access public
     * @param array|string $tables
     * @return ActiveRecord
     */
    abstract protected function fromTablesStatement($tables);

    /**
     * Limit string
     *
     * Generates a platform-specific LIMIT clause
     *
     * @access  public
     * @param string  $sql the sql query string
     * @param integer $limit the number of rows to limit the query to
     * @param integer $offset the offset value
     * @return string
     */
    abstract protected function limitStatement($sql, $limit, $offset);

    // --------------------------------------------------------------------

    /**
     * Select
     *
     * Generates the SELECT portion of the query
     *
     * @param string $select
     * @param mixed  $escape
     * @return ActiveRecord
     */
    public function select($select = '*', $escape = null)
    {
        if (is_string($select)) {
            $select = explode(',', $select);
        }

        foreach ($select as $val) {
            $val = trim($val);

            if ($val != '') {
                $this->ar_select[]    = $val;
                $this->ar_no_escape[] = $escape;

                if ($this->ar_caching === true) {
                    $this->ar_cache_select[]    = $val;
                    $this->ar_cache_exists[]    = 'select';
                    $this->ar_cache_no_escape[] = $escape;
                }
            }
        }
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Select Max
     *
     * Generates a SELECT MAX(field) portion of a query
     *
     * @param  string $select the field
     * @param  string $alias an alias
     * @return  object
     */
    public function selectMax($select = '', $alias = '')
    {
        return $this->maxMinAvgSum($select, $alias, 'MAX');
    }

    // --------------------------------------------------------------------

    /**
     * Select Min
     *
     * Generates a SELECT MIN(field) portion of a query
     *
     * @param  string $select the field
     * @param  string $alias an alias
     * @return  ActiveRecord
     */
    public function selectMin($select = '', $alias = '')
    {
        return $this->maxMinAvgSum($select, $alias, 'MIN');
    }

    // --------------------------------------------------------------------

    /**
     * Select Average
     *
     * Generates a SELECT AVG(field) portion of a query
     *
     * @param  string $select the field
     * @param  string $alias an alias
     * @return  ActiveRecord
     */
    public function selectAvg($select = '', $alias = '')
    {
        return $this->maxMinAvgSum($select, $alias, 'AVG');
    }

    // --------------------------------------------------------------------

    /**
     * Select Sum
     *
     * Generates a SELECT SUM(field) portion of a query
     *
     * @param  string $select the field
     * @param  string $alias an alias
     * @return  object
     */
    public function selectSum($select = '', $alias = '')
    {
        return $this->maxMinAvgSum($select, $alias, 'SUM');
    }

    // --------------------------------------------------------------------

    /**
     * Processing Function for the four functions above:
     *
     *  selectMax()
     *  selectMin()
     *  selectAvg()
     *  selectSum()
     *
     * @param string $select the field
     * @param string $alias an alias
     * @param string $type
     * @return  ActiveRecord
     */
    protected function maxMinAvgSum($select = '', $alias = '', $type = 'MAX')
    {
        if (!is_string($select) || $select == '') {
            $this->displayError('db_invalid_query');
        }

        $type = strtoupper($type);

        if (!in_array($type, array('MAX', 'MIN', 'AVG', 'SUM'))) {
            \CI\Core\show_error('Invalid function type: ' . $type);
        }

        if ($alias == '') {
            $alias = $this->createAliasFromTable(trim($select));
        }

        $sql = $type . '(' . $this->protectIdentifiers(trim($select)) . ') AS ' . $alias;

        $this->ar_select[] = $sql;

        if ($this->ar_caching === true) {
            $this->ar_cache_select[] = $sql;
            $this->ar_cache_exists[] = 'select';
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Determines the alias name based on the table
     *
     * @param string $item
     * @return string
     */
    protected function createAliasFromTable($item)
    {
        if (strpos($item, '.') !== false) {
            return end(explode('.', $item));
        }

        return $item;
    }

    // --------------------------------------------------------------------

    /**
     * DISTINCT
     *
     * Sets a flag which tells the query string compiler to add DISTINCT
     *
     * @param bool $val
     * @return ActiveRecord
     */
    public function distinct($val = true)
    {
        $this->ar_distinct = (is_bool($val)) ? $val : true;
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * From
     *
     * Generates the FROM portion of the query
     *
     * @param mixed $from can be a string or array
     * @return object
     */
    public function from($from)
    {
        foreach ((array)$from as $val) {
            if (strpos($val, ',') !== false) {
                foreach (explode(',', $val) as $v) {
                    $v = trim($v);
                    $this->trackAliases($v);

                    $this->ar_from[] = $this->protectIdentifiers($v, true, null, false);

                    if ($this->ar_caching === true) {
                        $this->ar_cache_from[]   = $this->protectIdentifiers($v, true, null, false);
                        $this->ar_cache_exists[] = 'from';
                    }
                }

            } else {
                $val = trim($val);

                // Extract any aliases that might exist.  We use this information
                // in the protect_identifiers to know whether to add a table prefix
                $this->trackAliases($val);

                $this->ar_from[] = $this->protectIdentifiers($val, true, null, false);

                if ($this->ar_caching === true) {
                    $this->ar_cache_from[]   = $this->protectIdentifiers($val, true, null, false);
                    $this->ar_cache_exists[] = 'from';
                }
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Join
     *
     * Generates the JOIN portion of the query
     *
     * @param string $table
     * @param string $cond the join condition
     * @param string $type the type of join
     * @return ActiveRecord
     */
    public function join($table, $cond, $type = '')
    {
        if ($type != '') {
            $type = strtoupper(trim($type));

            if (!in_array($type, array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'))) {
                $type = '';
            } else {
                $type .= ' ';
            }
        }

        // Extract any aliases that might exist.  We use this information
        // in the protect_identifiers to know whether to add a table prefix
        $this->trackAliases($table);

        // Strip apart the condition and protect the identifiers
        if (preg_match('/([\w\.]+)([\W\s]+)(.+)/', $cond, $match)) {
            $match[1] = $this->protectIdentifiers($match[1]);
            $match[3] = $this->protectIdentifiers($match[3]);

            $cond = $match[1] . $match[2] . $match[3];
        }

        // Assemble the JOIN statement
        $join = $type . 'JOIN ' . $this->protectIdentifiers($table, true, null, false) . ' ON ' . $cond;

        $this->ar_join[] = $join;
        if ($this->ar_caching === true) {
            $this->ar_cache_join[]   = $join;
            $this->ar_cache_exists[] = 'join';
        }

        return $this;
    }

    /**
     * OR Where
     *
     * Generates the WHERE portion of the query. Separates
     * multiple calls with OR
     *
     * @param mixed $key
     * @param mixed $value
     * @param bool  $escape
     * @return  ActiveRecord
     */
    public function orWhere($key, $value = null, $escape = true)
    {
        return $this->where($key, $value, $escape, 'OR ');
    }

    // --------------------------------------------------------------------

    // --------------------------------------------------------------------

    /**
     * Where
     *
     * Called by where() or orWhere()
     *
     * @param mixed  $key
     * @param mixed  $value
     * @param string $escape
     * @param string $type
     * @return  ActiveRecord
     */
    public function where($key, $value = null, $escape = null, $type = 'AND ')
    {
        if (!is_array($key)) {
            $key = array($key => $value);
        }

        // If the escape value was not set will will base it on the global setting
        if (!is_bool($escape)) {
            $escape = $this->protect_identifiers;
        }

        foreach ($key as $k => $v) {
            $prefix = (count($this->ar_where) == 0 && count($this->ar_cache_where) == 0) ? '' : $type;

            if (is_null($v) && !$this->hasOperator($k)) {
                // value appears not to have been set, assign the test to IS NULL
                $k .= ' IS NULL';
            }

            if (!is_null($v)) {
                if ($escape === true) {
                    $k = $this->protectIdentifiers($k, false, $escape);

                    $v = ' ' . $this->escape($v);
                }

                if (!$this->hasOperator($k)) {
                    $k .= ' = ';
                }
            } else {
                $k = $this->protectIdentifiers($k, false, $escape);
            }

            $this->ar_where[] = $prefix . $k . $v;

            if ($this->ar_caching === true) {
                $this->ar_cache_where[]  = $prefix . $k . $v;
                $this->ar_cache_exists[] = 'where';
            }

        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Where_in_or
     *
     * Generates a WHERE field IN ('item', 'item') SQL query joined with
     * OR if appropriate
     *
     * @param  string $key The field to search
     * @param  array  $values The values searched on
     * @return  object
     */
    public function orWhereIn($key = null, $values = null)
    {
        return $this->whereIn($key, $values, false, 'OR ');
    }

    // --------------------------------------------------------------------

    /**
     * Where_not_in
     *
     * Generates a WHERE field NOT IN ('item', 'item') SQL query joined
     * with AND if appropriate
     *
     * @param  string $key The field to search
     * @param  array  $values The values searched on
     * @return  object
     */
    public function whereNotIn($key = null, $values = null)
    {
        return $this->whereIn($key, $values, true);
    }

    // --------------------------------------------------------------------

    /**
     * Where_not_in_or
     *
     * Generates a WHERE field NOT IN ('item', 'item') SQL query joined
     * with OR if appropriate
     *
     * @param string $key The field to search
     * @param array  $values The values searched on
     * @return ActiveRecord
     */
    public function orWhereNotIn($key = null, $values = null)
    {
        return $this->whereIn($key, $values, true, 'OR ');
    }

    // --------------------------------------------------------------------

    /**
     * Where_in
     *
     * Called by whereIn, where_in_or, whereNotIn, where_not_in_or
     *
     * @param string  $key The field to search
     * @param array   $values The values searched on
     * @param boolean $not If the statement would be IN or NOT IN
     * @param string  $type
     * @return ActiveRecord
     */
    public function whereIn($key = null, $values = null, $not = false, $type = 'AND ')
    {
        if ($key === null || $values === null) {
            return this;
        }

        if (!is_array($values)) {
            $values = array($values);
        }

        $not = ($not) ? ' NOT' : '';

        foreach ($values as $value) {
            $this->ar_wherein[] = $this->escape($value);
        }

        $prefix = (count($this->ar_where) == 0) ? '' : $type;

        $where_in = $prefix . $this->protectIdentifiers($key) . $not . " IN (" .
            implode(
                ", ",
                $this->ar_wherein
            ) . ") ";

        $this->ar_where[] = $where_in;
        if ($this->ar_caching === true) {
            $this->ar_cache_where[]  = $where_in;
            $this->ar_cache_exists[] = 'where';
        }

        // reset the array for multiple calls
        $this->ar_wherein = array();
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Not Like
     *
     * Generates a NOT LIKE portion of the query. Separates
     * multiple calls with AND
     *
     * @param mixed  $field
     * @param mixed  $match
     * @param string $side
     * @return ActiveRecord
     */
    public function notLike($field, $match = '', $side = 'both')
    {
        return $this->like($field, $match, $side, 'AND ', 'NOT');
    }

    // --------------------------------------------------------------------

    /**
     * OR Like
     *
     * Generates a %LIKE% portion of the query. Separates
     * multiple calls with OR
     *
     * @param mixed  $field
     * @param mixed  $match
     * @param string $side
     *
     * @return  object
     */
    public function orLike($field, $match = '', $side = 'both')
    {
        return $this->like($field, $match, $side, 'OR ');
    }

    // --------------------------------------------------------------------

    /**
     * OR Not Like
     *
     * Generates a NOT LIKE portion of the query. Separates
     * multiple calls with OR
     *
     * @param mixed  $field
     * @param mixed  $match
     * @param string $side
     * @return ActiveRecord
     */
    public function orNotLike($field, $match = '', $side = 'both')
    {
        return $this->like($field, $match, $side, 'OR ', 'NOT');
    }

    // --------------------------------------------------------------------

    /**
     * Like
     *
     * Called by like() or orlike()
     *
     * @param mixed  $field
     * @param mixed  $match
     * @param string $side
     * @param string $type
     * @param string $not
     * @return  object
     */
    public function like($field, $match = '', $side = 'both', $type = 'AND ', $not = '')
    {
        if (!is_array($field)) {
            $field = array($field => $match);
        }

        foreach ($field as $k => $v) {
            $k = $this->protectIdentifiers($k);

            $prefix = (count($this->ar_like) == 0) ? '' : $type;

            $v = $this->escapeLikeStr($v);

            if ($side == 'none') {
                $like_statement = $prefix . " $k $not LIKE '{$v}'";
            } elseif ($side == 'before') {
                $like_statement = $prefix . " $k $not LIKE '%{$v}'";
            } elseif ($side == 'after') {
                $like_statement = $prefix . " $k $not LIKE '{$v}%'";
            } else {
                $like_statement = $prefix . " $k $not LIKE '%{$v}%'";
            }

            // some platforms require an escape sequence definition for LIKE wildcards
            if ($this->like_escape_str != '') {
                $like_statement = $like_statement . sprintf($this->like_escape_str, $this->like_escape_chr);
            }

            $this->ar_like[] = $like_statement;
            if ($this->ar_caching === true) {
                $this->ar_cache_like[]   = $like_statement;
                $this->ar_cache_exists[] = 'like';
            }

        }
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * GROUP BY
     *
     * @param string $by
     * @return ActiveRecord
     */
    public function groupBy($by)
    {
        if (is_string($by)) {
            $by = explode(',', $by);
        }

        foreach ($by as $val) {
            $val = trim($val);

            if ($val != '') {
                $this->ar_groupby[] = $this->protectIdentifiers($val);

                if ($this->ar_caching === true) {
                    $this->ar_cache_groupby[] = $this->protectIdentifiers($val);
                    $this->ar_cache_exists[]  = 'groupby';
                }
            }
        }
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Sets the OR HAVING value
     *
     * Separates multiple calls with OR
     *
     * @param string $key
     * @param string $value
     * @param bool   $escape
     *
     * @return  object
     */
    public function orHaving($key, $value = '', $escape = true)
    {
        return $this->having($key, $value, $escape, 'OR ');
    }

    // --------------------------------------------------------------------

    /**
     * Sets the HAVING values
     *
     * Called by having() or orHaving()
     *
     * @param string $key
     * @param string $value
     * @param bool   $escape
     * @param string $type
     *
     * @return ActiveRecord
     */
    public function having($key, $value = '', $escape = true, $type = 'AND ')
    {
        if (!is_array($key)) {
            $key = array($key => $value);
        }

        foreach ($key as $k => $v) {
            $prefix = (count($this->ar_having) == 0) ? '' : $type;

            if ($escape === true) {
                $k = $this->protectIdentifiers($k);
            }

            if (!$this->hasOperator($k)) {
                $k .= ' = ';
            }

            if ($v != '') {
                $v = ' ' . $this->escape($v);
            }

            $this->ar_having[] = $prefix . $k . $v;
            if ($this->ar_caching === true) {
                $this->ar_cache_having[] = $prefix . $k . $v;
                $this->ar_cache_exists[] = 'having';
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Sets the ORDER BY value
     *
     * @param string $orderby
     * @param string $direction direction: asc or desc
     * @return ActiveRecord
     */
    public function orderBy($orderby, $direction = '')
    {
        if (strtolower($direction) == 'random') {
            $orderby   = ''; // Random results want or don't need a field name
            $direction = $this->random_keyword;
        } elseif (trim($direction) != '') {
            $direction = (in_array(
                strtoupper(trim($direction)),
                array('ASC', 'DESC'),
                true
            )) ? ' ' . $direction : ' ASC';
        }


        if (strpos($orderby, ',') !== false) {
            $temp = array();
            foreach (explode(',', $orderby) as $part) {
                $part = trim($part);
                if (!in_array($part, $this->ar_aliased_tables)) {
                    $part = $this->protectIdentifiers(trim($part));
                }

                $temp[] = $part;
            }

            $orderby = implode(', ', $temp);
        } else {
            if ($direction != $this->random_keyword) {
                $orderby = $this->protectIdentifiers($orderby);
            }
        }

        $orderby_statement = $orderby . $direction;

        $this->ar_orderby[] = $orderby_statement;
        if ($this->ar_caching === true) {
            $this->ar_cache_orderby[] = $orderby_statement;
            $this->ar_cache_exists[]  = 'orderby';
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Sets the LIMIT value
     *
     * @param integer $value the limit value
     * @param string  $offset the offset value
     * @return ActiveRecord
     */
    public function limit($value, $offset = '')
    {
        $this->ar_limit = (int)$value;

        if ($offset != '') {
            $this->ar_offset = (int)$offset;
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Sets the OFFSET value
     *
     * @param integer $offset the offset value
     * @return ActiveRecord
     */
    public function offset($offset)
    {
        $this->ar_offset = $offset;
        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * The "set" function.  Allows key/value pairs to be set for inserting or updating
     *
     * @param string  $key
     * @param string  $value
     * @param boolean $escape
     * @return ActiveRecord
     */
    public function set($key, $value = '', $escape = true)
    {
        $key = $this->objectToArray($key);

        if (!is_array($key)) {
            $key = array($key => $value);
        }

        foreach ($key as $k => $v) {
            if ($escape === false) {
                $this->ar_set[$this->protectIdentifiers($k)] = $v;
            } else {
                $this->ar_set[$this->protectIdentifiers($k, false, true)] = $this->escape($v);
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Get
     *
     * Compiles the select statement based on the other functions called
     * and runs the query
     *
     * @param string $table the table
     * @param string $limit the limit clause
     * @param string $offset the offset clause
     * @return ActiveRecord
     */
    public function get($table = '', $limit = null, $offset = null)
    {
        if ($table != '') {
            $this->trackAliases($table);
            $this->from($table);
        }

        if (!is_null($limit)) {
            $this->limit($limit, $offset);
        }

        $sql = $this->compileSelect();

        $result = $this->query($sql);
        $this->resetSelect();
        return $result;
    }

    /**
     * "Count All Results" query
     *
     * Generates a platform-specific query string that counts all records
     * returned by an Active Record query.
     *
     * @param  string $table
     * @return string
     */
    public function countAllResults($table = '')
    {
        if ($table != '') {
            $this->trackAliases($table);
            $this->from($table);
        }

        $sql = $this->compileSelect($this->count_string . $this->protectIdentifiers('numrows'));

        $query = $this->query($sql);
        $this->resetSelect();

        if ($query->num_rows() == 0) {
            return 0;
        }

        $row = $query->row();
        return (int)$row->numrows;
    }

    // --------------------------------------------------------------------

    /**
     * Get_Where
     *
     * Allows the where clause, limit and offset to be added directly
     *
     * @param string $table
     * @param string $where the where clause
     * @param string $limit the limit clause
     * @param string $offset the offset clause
     * @return Result
     */
    public function getWhere($table = '', $where = null, $limit = null, $offset = null)
    {
        if ($table != '') {
            $this->from($table);
        }

        if (!is_null($where)) {
            $this->where($where);
        }

        if (!is_null($limit)) {
            $this->limit($limit, $offset);
        }

        $sql = $this->compileSelect();

        $result = $this->query($sql);
        $this->resetSelect();
        return $result;
    }

    // --------------------------------------------------------------------

    /**
     * Insert_Batch
     *
     * Compiles batch insert strings and runs the queries
     *
     * @param  string $table the table to retrieve the results from
     * @param  array  $set an associative array of insert values
     * @return ActiveRecord
     */
    public function insertBatch($table = '', $set = null)
    {
        if (!is_null($set)) {
            $this->setInsertBatch($set);
        }

        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                //No valid data array.  Folds in cases where keys and values did not match up
                return $this->displayError('db_must_use_set');
            }
            return false;
        }

        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        }

        // Batch this baby
        for ($i = 0, $total = count($this->ar_set); $i < $total; $i = $i + 100) {

            $sql = $this->insertBatchStatement(
                $this->protectIdentifiers($table, true, null, false),
                $this->ar_keys,
                array_slice($this->ar_set, $i, 100)
            );

            $this->query($sql);
        }

        $this->resetWrite();


        return true;
    }

    // --------------------------------------------------------------------

    /**
     * The "setInsertBatch" function.  Allows key/value pairs to be set for batch inserts
     *
     * @param array|string $key
     * @param string       $value
     * @param boolean      $escape
     * @return ActiveRecord
     */
    public function setInsertBatch($key, $value = '', $escape = true)
    {
        $key = $this->objectToArrayBatch($key);

        if (!is_array($key)) {
            $key = array($key => $value);
        }

        $keys = array_keys(current($key));
        sort($keys);

        foreach ($key as $row) {
            if (count(array_diff($keys, array_keys($row))) > 0 || count(array_diff(array_keys($row), $keys)) > 0) {
                // batch function above returns an error on an empty array
                $this->ar_set[] = array();
                return $this;
            }

            ksort($row); // puts $row in the same order as our keys

            if ($escape === false) {
                $this->ar_set[] = '(' . implode(',', $row) . ')';
            } else {
                $clean = array();

                foreach ($row as $value) {
                    $clean[] = $this->escape($value);
                }

                $this->ar_set[] = '(' . implode(',', $clean) . ')';
            }
        }

        foreach ($keys as $k) {
            $this->ar_keys[] = $this->protectIdentifiers($k);
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Insert
     *
     * Compiles an insert string and runs the query
     *
     * @param string $table the table to insert data into
     * @param array  $set an associative array of insert values
     * @return ActiveRecord
     */
    public function insert($table = '', $set = null)
    {
        if (!is_null($set)) {
            $this->set($set);
        }

        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->displayError('db_must_use_set');
            }
            return false;
        }

        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        }

        $sql = $this->insertStatement(
            $this->protectIdentifiers($table, true, null, false),
            array_keys($this->ar_set),
            array_values($this->ar_set)
        );

        $this->resetWrite();
        return $this->query($sql);
    }

    // --------------------------------------------------------------------

    /**
     * Replace
     *
     * Compiles an replace into string and runs the query
     *
     * @param string $table the table to replace data into
     * @param array  $set an associative array of insert values
     * @return ActiveRecord
     */
    public function replace($table = '', $set = null)
    {
        if (!is_null($set)) {
            $this->set($set);
        }

        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->displayError('db_must_use_set');
            }
            return false;
        }

        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        }

        $sql = $this->replaceStatement(
            $this->protectIdentifiers($table, true, null, false),
            array_keys($this->ar_set),
            array_values($this->ar_set)
        );

        $this->resetWrite();
        return $this->query($sql);
    }

    // --------------------------------------------------------------------

    /**
     * Update
     *
     * Compiles an update string and runs the query
     *
     * @param string $table the table to retrieve the results from
     * @param array  $set an associative array of update values
     * @param mixed  $where the where clause
     * @param string $limit
     * @return ActiveRecord
     */
    public function update($table = '', $set = null, $where = null, $limit = null)
    {
        // Combine any cached components with the current statements
        $this->mergeCache();

        if (!is_null($set)) {
            $this->set($set);
        }

        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->displayError('db_must_use_set');
            }
            return false;
        }

        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        }

        if ($where != null) {
            $this->where($where);
        }

        if ($limit != null) {
            $this->limit($limit);
        }

        $sql = $this->updateStatement(
            $this->protectIdentifiers($table, true, null, false),
            $this->ar_set,
            $this->ar_where,
            $this->ar_orderby,
            $this->ar_limit
        );

        $this->resetWrite();
        return $this->query($sql);
    }


    // --------------------------------------------------------------------

    /**
     * Update_Batch
     *
     * Compiles an update string and runs the query
     *
     * @param string $table the table to retrieve the results from
     * @param array  $set an associative array of update values
     * @param string $index the where key
     * @return ActiveRecord
     */
    public function updateBatch($table = '', $set = null, $index = null)
    {
        // Combine any cached components with the current statements
        $this->mergeCache();

        if (is_null($index)) {
            if ($this->db_debug) {
                return $this->displayError('db_must_use_index');
            }

            return false;
        }

        if (!is_null($set)) {
            $this->setUpdateBatch($set, $index);
        }

        if (count($this->ar_set) == 0) {
            if ($this->db_debug) {
                return $this->displayError('db_must_use_set');
            }

            return false;
        }

        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        }

        // Batch this baby
        for ($i = 0, $total = count($this->ar_set); $i < $total; $i = $i + 100) {
            $sql = $this->updateBatchStatement(
                $this->protectIdentifiers($table, true, null, false),
                array_slice($this->ar_set, $i, 100),
                $this->protectIdentifiers($index),
                $this->ar_where
            );

            $this->query($sql);
        }

        $this->resetWrite();

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * The "setUpdateBatch" function.  Allows key/value pairs to be set for batch updating
     *
     * @param array   $key
     * @param string  $index
     * @param boolean $escape
     * @return ActiveRecord
     */
    protected function setUpdateBatch($key, $index = '', $escape = true)
    {
        $key = $this->objectToArrayBatch($key);

        if (!is_array($key)) {
            // @todo error
        }

        foreach ($key as $v) {
            $index_set = false;
            $clean     = array();

            foreach ($v as $k2 => $v2) {
                if ($k2 == $index) {
                    $index_set = true;
                } else {
                    $not[] = $k2 . '-' . $v2;
                }

                if ($escape === false) {
                    $clean[$this->protectIdentifiers($k2)] = $v2;
                } else {
                    $clean[$this->protectIdentifiers($k2)] = $this->escape($v2);
                }
            }

            if ($index_set == false) {
                return $this->displayError('db_batch_missing_index');
            }

            $this->ar_set[] = $clean;
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Empty Table
     *
     * Compiles a delete string and runs "DELETE FROM table"
     *
     * @param string $table the table to empty
     * @return ActiveRecord
     */
    public function emptyTable($table = '')
    {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        } else {
            $table = $this->protectIdentifiers($table, true, null, false);
        }

        $sql = $this->deleteStatement($table);

        $this->resetWrite();

        return $this->query($sql);
    }

    // --------------------------------------------------------------------

    /**
     * Truncate
     *
     * Compiles a truncate string and runs the query
     * If the database does not support the truncate() command
     * This function maps to "DELETE FROM table"
     *
     * @param  string $table the table to truncate
     * @return  object
     */
    public function truncate($table = '')
    {
        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        } else {
            $table = $this->protectIdentifiers($table, true, null, false);
        }

        $sql = $this->truncateStatement($table);

        $this->resetWrite();

        return $this->query($sql);
    }

    // --------------------------------------------------------------------

    /**
     * Delete
     *
     * Compiles a delete string and runs the query
     *
     * @param mixed   $table the table(s) to delete from. String or array
     * @param mixed   $where the where clause
     * @param mixed   $limit the limit clause
     * @param boolean $reset_data
     * @return ActiveRecord
     */
    public function delete($table = '', $where = '', $limit = null, $reset_data = true)
    {
        // Combine any cached components with the current statements
        $this->mergeCache();

        if ($table == '') {
            if (!isset($this->ar_from[0])) {
                if ($this->db_debug) {
                    return $this->displayError('db_must_set_table');
                }
                return false;
            }

            $table = $this->ar_from[0];
        } elseif (is_array($table)) {
            foreach ($table as $single_table) {
                $this->delete($single_table, $where, $limit, false);
            }

            $this->resetWrite();
            return $this;
        } else {
            $table = $this->protectIdentifiers($table, true, null, false);
        }

        if ($where != '') {
            $this->where($where);
        }

        if ($limit != null) {
            $this->limit($limit);
        }

        if (count($this->ar_where) == 0 && count($this->ar_wherein) == 0 && count($this->ar_like) == 0) {
            if ($this->db_debug) {
                return $this->displayError('db_del_must_use_where');
            }

            return false;
        }

        $sql = $this->deleteStatement($table, $this->ar_where, $this->ar_like, $this->ar_limit);

        if ($reset_data) {
            $this->resetWrite();
        }

        return $this->query($sql);
    }

    // --------------------------------------------------------------------

    /**
     * DB Prefix
     *
     * Prepends a database prefix if one exists in configuration
     *
     * @param  string $table the table
     * @return  string
     */
    public function dbPrefix($table = '')
    {
        if ($table == '') {
            $this->displayError('db_table_name_required');
        }

        return $this->dbprefix . $table;
    }

    // --------------------------------------------------------------------

    /**
     * Set DB Prefix
     *
     * Set's the DB Prefix to something new without needing to reconnect
     *
     * @param  string $prefix the prefix
     * @return  string
     */
    public function setDbPrefix($prefix = '')
    {
        return $this->dbprefix = $prefix;
    }

    // --------------------------------------------------------------------

    /**
     * Track Aliases
     *
     * Used to track SQL statements written with aliased tables.
     *
     * @param  string $table The table to inspect
     * @return  string
     */
    protected function trackAliases($table)
    {
        if (is_array($table)) {
            foreach ($table as $t) {
                $this->trackAliases($t);
            }
            return '';
        }

        // Does the string contain a comma?  If so, we need to separate
        // the string into discreet statements
        if (strpos($table, ',') !== false) {
            return $this->trackAliases(explode(',', $table));
        }

        // if a table alias is used we can recognize it by a space
        if (strpos($table, " ") !== false) {
            // if the alias is written with the AS keyword, remove it
            $table = preg_replace('/\s+AS\s+/i', ' ', $table);

            // Grab the alias
            $table = trim(strrchr($table, " "));

            // Store the alias, if it doesn't already exist
            if (!in_array($table, $this->ar_aliased_tables)) {
                $this->ar_aliased_tables[] = $table;
            }
        }
        return '';
    }

    // --------------------------------------------------------------------

    /**
     * Compile the SELECT statement
     *
     * Generates a query string based on which functions were used.
     * Should not be called directly.  The get() function calls it.
     *
     * @param $select_override
     *
     * @return  string
     */
    protected function compileSelect($select_override = false)
    {
        // Combine any cached components with the current statements
        $this->mergeCache();

        // ----------------------------------------------------------------

        // Write the "select" portion of the query

        if ($select_override !== false) {
            $sql = $select_override;
        } else {
            $sql = (!$this->ar_distinct) ? 'SELECT ' : 'SELECT DISTINCT ';

            if (count($this->ar_select) == 0) {
                $sql .= '*';
            } else {
                // Cycle through the "select" portion of the query and prep each column name.
                // The reason we protect identifiers here rather then in the select() function
                // is because until the user calls the from() function we don't know if there are aliases
                foreach ($this->ar_select as $key => $val) {
                    $no_escape             = isset($this->ar_no_escape[$key]) ? $this->ar_no_escape[$key] : null;
                    $this->ar_select[$key] = $this->protectIdentifiers($val, false, $no_escape);
                }

                $sql .= implode(', ', $this->ar_select);
            }
        }

        // ----------------------------------------------------------------

        // Write the "FROM" portion of the query

        if (count($this->ar_from) > 0) {
            $sql .= "\nFROM ";

            $sql .= $this->fromTablesStatement($this->ar_from);
        }

        // ----------------------------------------------------------------

        // Write the "JOIN" portion of the query

        if (count($this->ar_join) > 0) {
            $sql .= "\n";

            $sql .= implode("\n", $this->ar_join);
        }

        // ----------------------------------------------------------------

        // Write the "WHERE" portion of the query

        if (count($this->ar_where) > 0 || count($this->ar_like) > 0) {
            $sql .= "\nWHERE ";
        }

        $sql .= implode("\n", $this->ar_where);

        // ----------------------------------------------------------------

        // Write the "LIKE" portion of the query

        if (count($this->ar_like) > 0) {
            if (count($this->ar_where) > 0) {
                $sql .= "\nAND ";
            }

            $sql .= implode("\n", $this->ar_like);
        }

        // ----------------------------------------------------------------

        // Write the "GROUP BY" portion of the query

        if (count($this->ar_groupby) > 0) {
            $sql .= "\nGROUP BY ";

            $sql .= implode(', ', $this->ar_groupby);
        }

        // ----------------------------------------------------------------

        // Write the "HAVING" portion of the query

        if (count($this->ar_having) > 0) {
            $sql .= "\nHAVING ";
            $sql .= implode("\n", $this->ar_having);
        }

        // ----------------------------------------------------------------

        // Write the "ORDER BY" portion of the query

        if (count($this->ar_orderby) > 0) {
            $sql .= "\nORDER BY ";
            $sql .= implode(', ', $this->ar_orderby);

            if ($this->ar_order !== false) {
                $sql .= ($this->ar_order == 'desc') ? ' DESC' : ' ASC';
            }
        }

        // ----------------------------------------------------------------

        // Write the "LIMIT" portion of the query

        if (is_numeric($this->ar_limit)) {
            $sql .= "\n";
            $sql = $this->limitStatement($sql, $this->ar_limit, $this->ar_offset);
        }

        return $sql;
    }

    // --------------------------------------------------------------------

    /**
     * Object to Array
     *
     * Takes an object as input and converts the class variables to array key/vals
     *
     * @param  object
     * @return  array|mixed
     */
    public function objectToArray($object)
    {
        if (!is_object($object)) {
            return $object;
        }

        $array = array();
        foreach (get_object_vars($object) as $key => $val) {
            // There are some built in keys we need to ignore for this conversion
            if (!is_object($val) && !is_array($val) && $key != '_parent_name') {
                $array[$key] = $val;
            }
        }

        return $array;
    }

    // --------------------------------------------------------------------

    /**
     * Object to Array
     *
     * Takes an object as input and converts the class variables to array key/vals
     *
     * @param  object
     * @return  array|string
     */
    public function objectToArrayBatch($object)
    {
        if (!is_object($object)) {
            return $object;
        }

        $array  = array();
        $out    = get_object_vars($object);
        $fields = array_keys($out);

        foreach ($fields as $val) {
            // There are some built in keys we need to ignore for this conversion
            if ($val != '_parent_name') {

                $i = 0;
                foreach ($out[$val] as $data) {
                    $array[$i][$val] = $data;
                    $i++;
                }
            }
        }

        return $array;
    }

    // --------------------------------------------------------------------

    /**
     * Start Cache
     *
     * Starts AR caching
     *
     * @return  void
     */
    public function startCache()
    {
        $this->ar_caching = true;
    }

    // --------------------------------------------------------------------

    /**
     * Stop Cache
     *
     * Stops AR caching
     *
     * @return  void
     */
    public function stopCache()
    {
        $this->ar_caching = false;
    }

    // --------------------------------------------------------------------

    /**
     * Flush Cache
     *
     * Empties the AR cache
     *
     * @access  public
     * @return  void
     */
    public function flushCache()
    {
        $this->resetRun(
            array(
                'ar_cache_select'    => array(),
                'ar_cache_from'      => array(),
                'ar_cache_join'      => array(),
                'ar_cache_where'     => array(),
                'ar_cache_like'      => array(),
                'ar_cache_groupby'   => array(),
                'ar_cache_having'    => array(),
                'ar_cache_orderby'   => array(),
                'ar_cache_set'       => array(),
                'ar_cache_exists'    => array(),
                'ar_cache_no_escape' => array()
            )
        );
    }

    // --------------------------------------------------------------------

    /**
     * Merge Cache
     *
     * When called, this function merges any cached AR arrays with
     * locally called ones.
     *
     * @return  void
     */
    protected function mergeCache()
    {
        if (count($this->ar_cache_exists) == 0) {
            return;
        }

        foreach ($this->ar_cache_exists as $val) {
            $ar_variable  = 'ar_' . $val;
            $ar_cache_var = 'ar_cache_' . $val;

            if (count($this->$ar_cache_var) == 0) {
                continue;
            }

            $this->$ar_variable = array_unique(array_merge($this->$ar_cache_var, $this->$ar_variable));
        }

        // If we are "protecting identifiers" we need to examine the "from"
        // portion of the query to determine if there are any aliases
        if ($this->protect_identifiers === true && count($this->ar_cache_from) > 0) {
            $this->trackAliases($this->ar_from);
        }

        $this->ar_no_escape = $this->ar_cache_no_escape;
    }

    // --------------------------------------------------------------------

    /**
     * Resets the active record values.  Called by the get() function
     *
     * @param  array $ar_reset_items An array of fields to reset
     * @return  void
     */
    protected function resetRun($ar_reset_items)
    {
        foreach ($ar_reset_items as $item => $default_value) {
            if (!in_array($item, $this->ar_store_array)) {
                $this->$item = $default_value;
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Resets the active record values.  Called by the get() function
     *
     * @return  void
     */
    protected function resetSelect()
    {
        $ar_reset_items = array(
            'ar_select'         => array(),
            'ar_from'           => array(),
            'ar_join'           => array(),
            'ar_where'          => array(),
            'ar_like'           => array(),
            'ar_groupby'        => array(),
            'ar_having'         => array(),
            'ar_orderby'        => array(),
            'ar_wherein'        => array(),
            'ar_aliased_tables' => array(),
            'ar_no_escape'      => array(),
            'ar_distinct'       => false,
            'ar_limit'          => false,
            'ar_offset'         => false,
            'ar_order'          => false,
        );

        $this->resetRun($ar_reset_items);
    }

    // --------------------------------------------------------------------

    /**
     * Resets the active record "write" values.
     *
     * Called by the insert() update() insertBatch() updateBatch() and delete() functions
     *
     * @return  void
     */
    protected function resetWrite()
    {
        $ar_reset_items = array(
            'ar_set'     => array(),
            'ar_from'    => array(),
            'ar_where'   => array(),
            'ar_like'    => array(),
            'ar_orderby' => array(),
            'ar_keys'    => array(),
            'ar_limit'   => false,
            'ar_order'   => false
        );

        $this->resetRun($ar_reset_items);
    }
}

/* End of file DB_active_rec.php */
/* Location: ./system/database/Activrecord.php */
