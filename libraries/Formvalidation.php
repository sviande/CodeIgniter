<?php
namespace CI\Libraries;

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
 * Form Validation Class
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Validation
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/form_validation.html
 */
class FormValidation
{

    protected $CI;
    protected $field_data = array();
    protected $config_rules = array();
    protected $error_array = array();
    protected $error_messages = array();
    protected $error_prefix = '<p>';
    protected $error_suffix = '</p>';
    protected $error_string = '';
    protected $safe_form_data = false;

    /**
     * Constructor
     */
    public function __construct($rules = array())
    {
        $this->CI =& \CI\Core\get_instance();

        // Validation rules can be stored in a config file.
        $this->config_rules = $rules;

        // Automatically load the form helper
        $this->CI->load->helper('form');

        // Set the character encoding in MB.
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding($this->CI->config->item('charset'));
        }

        \CI\Core\log_message('debug', "Form Validation Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Set Rules
     *
     * This function takes an array of field names and validation
     * rules as input, validates the info, and stores it
     *
     * @access  public
     * @param  mixed  $field
     * @param  mixed  $label
     * @param  string $rules
     * @return  FormValidation
     */
    public function setRules($field, $label = '', $rules = '')
    {
        // No reason to set rules if we have no POST data
        if (count($_POST) == 0) {
            return $this;
        }

        // If an array was passed via the first parameter instead of indidual string
        // values we cycle through it and recursively call this function.
        if (is_array($field)) {
            foreach ($field as $row) {
                // Houston, we have a problem...
                if (!isset($row['field']) || !isset($row['rules'])) {
                    continue;
                }

                // If the field label wasn't passed we use the field name
                $label = (!isset($row['label'])) ? $row['field'] : $row['label'];

                // Here we go!
                $this->setRules($row['field'], $label, $row['rules']);
            }
            return $this;
        }

        // No fields? Nothing to do...
        if (!is_string($field) || !is_string($rules) || $field == '') {
            return $this;
        }

        // If the field label wasn't passed we use the field name
        $label = ($label == '') ? $field : $label;

        // Is the field name an array?  We test for the existence of a bracket "[" in
        // the field name to determine this.  If it is an array, we break it apart
        // into its components so that we can fetch the corresponding POST data later
        if (strpos($field, '[') !== false && preg_match_all('/\[(.*?)\]/', $field, $matches)) {
            // Note: Due to a bug in current() that affects some versions
            // of PHP we can not pass function call directly into it
            $x = explode('[', $field);
            $indexes[] = current($x);

            for ($i = 0; $i < count($matches['0']); $i++) {
                if ($matches['1'][$i] != '') {
                    $indexes[] = $matches['1'][$i];
                }
            }

            $is_array = true;
        } else {
            $indexes = array();
            $is_array = false;
        }

        // Build our master array
        $this->field_data[$field] = array(
            'field'    => $field,
            'label'    => $label,
            'rules'    => $rules,
            'is_array' => $is_array,
            'keys'     => $indexes,
            'postdata' => null,
            'error'    => ''
        );

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Set Error Message
     *
     * Lets users set their own error messages on the fly.  Note:  The key
     * name has to match the  function name that it corresponds to.
     *
     * @access  public
     * @param  string $lang
     * @param  string $val
     * @return  string
     */
    public function setMessage($lang, $val = '')
    {
        if (!is_array($lang)) {
            $lang = array($lang => $val);
        }

        $this->error_messages = array_merge($this->error_messages, $lang);

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Set The Error Delimiter
     *
     * Permits a prefix/suffix to be added to each error message
     *
     * @access  public
     * @param  string $suffix
     * @param  string $prefix
     * @return  FormValidation
     */
    public function setErrorDelimiters($prefix = '<p>', $suffix = '</p>')
    {
        $this->error_prefix = $prefix;
        $this->error_suffix = $suffix;

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Get Error Message
     *
     * Gets the error message associated with a particular field
     *
     * @access  public
     * @param  string $field the field name
     * @param         $prefix
     * @param         $suffix
     * @return  string
     */
    public function error($field = '', $prefix = '', $suffix = '')
    {
        if (!isset($this->field_data[$field]['error']) || $this->field_data[$field]['error'] == '') {
            return '';
        }

        if ($prefix == '') {
            $prefix = $this->error_prefix;
        }

        if ($suffix == '') {
            $suffix = $this->error_suffix;
        }

        return $prefix . $this->field_data[$field]['error'] . $suffix;
    }

    // --------------------------------------------------------------------

    /**
     * Error String
     *
     * Returns the error messages as a string, wrapped in the error delimiters
     *
     * @access  public
     * @param  string $prefix
     * @param  string $suffix
     * @return  string
     */
    public function errorString($prefix = '', $suffix = '')
    {
        // No errrors, validation passes!
        if (count($this->error_array) === 0) {
            return '';
        }

        if ($prefix == '') {
            $prefix = $this->error_prefix;
        }

        if ($suffix == '') {
            $suffix = $this->error_suffix;
        }

        // Generate the error string
        $str = '';
        foreach ($this->error_array as $val) {
            if ($val != '') {
                $str .= $prefix . $val . $suffix . "\n";
            }
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Run the Validator
     *
     * This function does all the work.
     *
     * @access  public
     * @param $group
     * @return  bool
     */
    public function run($group = '')
    {
        // Do we even have any data to process?  Mm?
        if (count($_POST) == 0) {
            return false;
        }

        // Does the fieldDataStatement array containing the validation rules exist?
        // If not, we look to see if they were assigned via a config file
        if (count($this->field_data) == 0) {
            // No validation rules?  We're done...
            if (count($this->config_rules) == 0) {
                return false;
            }

            // Is there a validation rule for the particular URI being accessed?
            $uri = ($group == '') ? trim($this->CI->uri->ruriString(), '/') : $group;

            if ($uri != '' && isset($this->config_rules[$uri])) {
                $this->setRules($this->config_rules[$uri]);
            } else {
                $this->setRules($this->config_rules);
            }

            // We're we able to set the rules correctly?
            if (count($this->field_data) == 0) {
                \CI\Core\log_message('debug', "Unable to find validation rules");
                return false;
            }
        }

        // Load the language file containing error messages
        $this->CI->lang->load('formvalidation');

        // Cycle through the rules for each field, match the
        // corresponding $_POST item and test for errors
        foreach ($this->field_data as $field => $row) {
            // Fetch the data from the corresponding $_POST array and cache it in the fieldDataStatement array.
            // Depending on whether the field name is an array or a string will determine where we get it from.

            if ($row['is_array'] == true) {
                $this->field_data[$field]['postdata'] = $this->reduceArray($_POST, $row['keys']);
            } else {
                if (isset($_POST[$field]) && $_POST[$field] != "") {
                    $this->field_data[$field]['postdata'] = $_POST[$field];
                }
            }

            $this->execute($row, explode('|', $row['rules']), $this->field_data[$field]['postdata']);
        }

        // Did we end up with any errors?
        $total_errors = count($this->error_array);

        if ($total_errors > 0) {
            $this->safe_form_data = true;
        }

        // Now we need to re-set the POST data with the new, processed data
        $this->resetPostArray();

        // No errors, validation passes!
        if ($total_errors == 0) {
            return true;
        }

        // Validation fails
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Traverse a multidimensional $_POST array index until the data is found
     *
     * @access  protected
     * @param  array   $array
     * @param  array   $keys
     * @param  integer $i
     * @return  mixed
     */
    protected function reduceArray($array, $keys, $i = 0)
    {
        if (is_array($array)) {
            if (isset($keys[$i])) {
                if (isset($array[$keys[$i]])) {
                    $array = $this->reduceArray($array[$keys[$i]], $keys, ($i + 1));
                } else {
                    return null;
                }
            } else {
                return $array;
            }
        }

        return $array;
    }

    // --------------------------------------------------------------------

    /**
     * Re-populate the _POST array with our finalized and processed data
     *
     * @access  private
     * @return  null
     */
    protected function resetPostArray()
    {
        foreach ($this->field_data as $row) {
            if (!is_null($row['postdata'])) {
                if ($row['is_array'] == false) {
                    if (isset($_POST[$row['field']])) {
                        $_POST[$row['field']] = $this->prep_for_form($row['postdata']);
                    }
                } else {
                    // start with a reference
                    $post_ref =& $_POST;

                    // before we assign values, make a reference to the right POST key
                    if (count($row['keys']) == 1) {
                        $post_ref =& $post_ref[current($row['keys'])];
                    } else {
                        foreach ($row['keys'] as $val) {
                            $post_ref =& $post_ref[$val];
                        }
                    }

                    if (is_array($row['postdata'])) {
                        $array = array();
                        foreach ($row['postdata'] as $k => $v) {
                            $array[$k] = $this->prep_for_form($v);
                        }

                        $post_ref = $array;
                    } else {
                        $post_ref = $this->prep_for_form($row['postdata']);
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Executes the Validation routines
     *
     * @access  protected
     * @param  array   $row
     * @param  array   $rules
     * @param  mixed   $postdata
     * @param  integer $cycles
     * @return  mixed
     */
    protected function execute($row, $rules, $postdata = null, $cycles = 0)
    {
        // If the $_POST data is an array we will run a recursive call
        if (is_array($postdata)) {
            foreach ($postdata as $val) {
                $this->execute($row, $rules, $val, $cycles);
                $cycles++;
            }

            return;
        }

        // --------------------------------------------------------------------

        // If the field is blank, but NOT required, no further tests are necessary
        $callback = false;
        if (!in_array('required', $rules) && is_null($postdata)) {
            // Before we bail out, does the rule contain a callback?
            if (preg_match("/(callback_\w+(\[.*?\])?)/", implode(' ', $rules), $match)) {
                $callback = true;
                $rules = (array('1' => $match[1]));
            } else {
                return;
            }
        }

        // --------------------------------------------------------------------

        // Isset Test. Typically this rule will only apply to checkboxes.
        if (is_null($postdata) && $callback == false) {
            if (in_array('isset', $rules, true) || in_array('required', $rules)) {
                // Set the message type
                $type = (in_array('required', $rules)) ? 'required' : 'isset';

                if (!isset($this->error_messages[$type])) {
                    if (false === ($line = $this->CI->lang->line($type))) {
                        $line = 'The field was not set';
                    }
                } else {
                    $line = $this->error_messages[$type];
                }

                // Build the error message
                $message = sprintf($line, $this->translateFieldname($row['label']));

                // Save the error message
                $this->field_data[$row['field']]['error'] = $message;

                if (!isset($this->error_array[$row['field']])) {
                    $this->error_array[$row['field']] = $message;
                }
            }

            return;
        }

        // --------------------------------------------------------------------

        // Cycle through each rule and run it
        foreach ($rules as $rule) {
            $_in_array = false;

            // We set the $postdata variable with the current data in our master array so that
            // each cycle of the loop is dealing with the processed data from the last cycle
            if ($row['is_array'] == true && is_array($this->field_data[$row['field']]['postdata'])) {
                // We shouldn't need this safety, but just in case there isn't an array index
                // associated with this cycle we'll bail out
                if (!isset($this->field_data[$row['field']]['postdata'][$cycles])) {
                    continue;
                }

                $postdata = $this->field_data[$row['field']]['postdata'][$cycles];
                $_in_array = true;
            } else {
                $postdata = $this->field_data[$row['field']]['postdata'];
            }

            // --------------------------------------------------------------------

            // Is the rule a callback?
            $callback = false;
            if (substr($rule, 0, 9) == 'callback_') {
                $rule = substr($rule, 9);
                $callback = true;
            }

            // Strip the parameter (if exists) from the rule
            // Rules can contain a parameter: max_length[5]
            $param = false;
            if (preg_match("/(.*?)\[(.*)\]/", $rule, $match)) {
                $rule = $match[1];
                $param = $match[2];
            }

            // Call the function that corresponds to the rule
            if ($callback === true) {
                if (!method_exists($this->CI, $rule)) {
                    continue;
                }

                // Run the function and grab the result
                $result = $this->CI->$rule($postdata, $param);

                // Re-assign the result to the master data array
                if ($_in_array == true) {
                    $this->field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                } else {
                    $this->field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                }

                // If the field isn't required and we just processed a callback we'll move on...
                if (!in_array('required', $rules, true) && $result !== false) {
                    continue;
                }
            } else {
                if (!method_exists($this, $rule)) {
                    // If our own wrapper function doesn't exist we see if a native PHP function does.
                    // Users can use any native PHP function call that has one param.
                    if (function_exists($rule)) {
                        $result = $rule($postdata);

                        if ($_in_array == true) {
                            $this->field_data[$row['field']]['postdata'][$cycles] = (is_bool(
                                $result
                            )) ? $postdata : $result;
                        } else {
                            $this->field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                        }
                    } else {
                        \CI\Core\log_message('debug', "Unable to find validation rule: " . $rule);
                    }

                    continue;
                }

                $result = $this->$rule($postdata, $param);

                if ($_in_array == true) {
                    $this->field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
                } else {
                    $this->field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
                }
            }

            // Did the rule test negatively?  If so, grab the error.
            if ($result === false) {
                if (!isset($this->error_messages[$rule])) {
                    if (false === ($line = $this->CI->lang->line($rule))) {
                        $line = 'Unable to access an error message corresponding to your field name.';
                    }
                } else {
                    $line = $this->error_messages[$rule];
                }

                // Is the parameter we are inserting into the error message the name
                // of another field?  If so we need to grab its "field label"
                if (isset($this->field_data[$param]) && isset($this->field_data[$param]['label'])) {
                    $param = $this->translateFieldname($this->field_data[$param]['label']);
                }

                // Build the error message
                $message = sprintf($line, $this->translateFieldname($row['label']), $param);

                // Save the error message
                $this->field_data[$row['field']]['error'] = $message;

                if (!isset($this->error_array[$row['field']])) {
                    $this->error_array[$row['field']] = $message;
                }

                return;
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Translate a field name
     *
     * @access  private
     * @param  string $fieldname the field name
     * @return  string
     */
    protected function translateFieldname($fieldname)
    {
        // Do we need to translate the field name?
        // We look for the prefix lang: to determine this
        if (substr($fieldname, 0, 5) == 'lang:') {
            // Grab the variable
            $line = substr($fieldname, 5);

            // Were we able to translate the field name?  If not we use $line
            if (false === ($fieldname = $this->CI->lang->line($line))) {
                return $line;
            }
        }

        return $fieldname;
    }

    // --------------------------------------------------------------------

    /**
     * Get the value from a form
     *
     * Permits you to repopulate a form field with the value it was submitted
     * with, or, if that value doesn't exist, with the default
     *
     * @access  public
     * @param  string $field the field name
     * @param  string $default
     * @return  string
     */
    public function setValue($field = '', $default = '')
    {
        if (!isset($this->field_data[$field])) {
            return $default;
        }

        // If the data is an array output them one at a time.
        //     E.g: form_input('name[]', setValue('name[]');
        if (is_array($this->field_data[$field]['postdata'])) {
            return array_shift($this->field_data[$field]['postdata']);
        }

        return $this->field_data[$field]['postdata'];
    }

    // --------------------------------------------------------------------

    /**
     * Set Select
     *
     * Enables pull-down lists to be set to the value the user
     * selected in the event of an error
     *
     * @access  public
     * @param  string $field
     * @param  string $value
     * @param bool    $default
     * @return  string
     */
    public function setSelect($field = '', $value = '', $default = false)
    {
        if (!isset($this->field_data[$field]) || !isset($this->field_data[$field]['postdata'])) {
            if ($default === true && count($this->field_data) === 0) {
                return ' selected="selected"';
            }
            return '';
        }

        $field = $this->field_data[$field]['postdata'];

        if (is_array($field)) {
            if (!in_array($value, $field)) {
                return '';
            }
        } else {
            if (($field == '' || $value == '') || ($field != $value)) {
                return '';
            }
        }

        return ' selected="selected"';
    }

    // --------------------------------------------------------------------

    /**
     * Set Radio
     *
     * Enables radio buttons to be set to the value the user
     * selected in the event of an error
     *
     * @access  public
     * @param string $field
     * @param string $value
     * @param bool   $default
     * @return  string
     */
    public function setRadio($field = '', $value = '', $default = false)
    {
        if (!isset($this->field_data[$field]) || !isset($this->field_data[$field]['postdata'])) {
            if ($default === true && count($this->field_data) === 0) {
                return ' checked="checked"';
            }
            return '';
        }

        $field = $this->field_data[$field]['postdata'];

        if (is_array($field)) {
            if (!in_array($value, $field)) {
                return '';
            }
        } else {
            if (($field == '' || $value == '') || ($field != $value)) {
                return '';
            }
        }

        return ' checked="checked"';
    }

    // --------------------------------------------------------------------

    /**
     * Set Checkbox
     *
     * Enables checkboxes to be set to the value the user
     * selected in the event of an error
     *
     * @access  public
     * @param  string $field
     * @param  string $value
     * @param  bool   $default
     * @return  string
     */
    public function setCheckbox($field = '', $value = '', $default = false)
    {
        if (!isset($this->field_data[$field]) || !isset($this->field_data[$field]['postdata'])) {
            if ($default === true && count($this->field_data) === 0) {
                return ' checked="checked"';
            }
            return '';
        }

        $field = $this->field_data[$field]['postdata'];

        if (is_array($field)) {
            if (!in_array($value, $field)) {
                return '';
            }
        } else {
            if (($field == '' || $value == '') || ($field != $value)) {
                return '';
            }
        }

        return ' checked="checked"';
    }

    // --------------------------------------------------------------------

    /**
     * Required
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function required($str)
    {
        if (!is_array($str)) {
            return (trim($str) == '') ? false : true;
        } else {
            return (!empty($str));
        }
    }

    // --------------------------------------------------------------------

    /**
     * Performs a Regular Expression match test.
     *
     * @access  public
     * @param  string $str
     * @param  string $regex
     * @return  bool
     */
    public function regexMatch($str, $regex)
    {
        if (!preg_match($regex, $str)) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Match one field to another
     *
     * @access  public
     * @param  string $str
     * @param  string $field
     * @return  bool
     */
    public function matches($str, $field)
    {
        if (!isset($_POST[$field])) {
            return false;
        }

        $field = $_POST[$field];

        return ($str !== $field) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Match one field to another
     *
     * @access  public
     * @param  string
     * @param  field
     * @return  bool
     */
    public function isUnique($str, $field)
    {
        list($table, $field) = explode('.', $field);
        $query = $this->CI->db->limit(1)->get_where($table, array($field => $str));

        return $query->num_rows() === 0;
    }

    // --------------------------------------------------------------------

    /**
     * Minimum Length
     *
     * @access  public
     * @param  string
     * @param  value
     * @return  bool
     */
    public function min_length($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen($str) < $val) ? false : true;
        }

        return (strlen($str) < $val) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Max Length
     *
     * @access  public
     * @param  string
     * @param  value
     * @return  bool
     */
    public function max_length($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen($str) > $val) ? false : true;
        }

        return (strlen($str) > $val) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Exact Length
     *
     * @access  public
     * @param  string
     * @param  value
     * @return  bool
     */
    public function exact_length($str, $val)
    {
        if (preg_match("/[^0-9]/", $val)) {
            return false;
        }

        if (function_exists('mb_strlen')) {
            return (mb_strlen($str) != $val) ? false : true;
        }

        return (strlen($str) != $val) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Valid Email
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function valid_email($str)
    {
        return (!preg_match(
            "/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix",
            $str
        )) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Valid Emails
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function valid_emails($str)
    {
        if (strpos($str, ',') === false) {
            return $this->valid_email(trim($str));
        }

        foreach (explode(',', $str) as $email) {
            if (trim($email) != '' && $this->valid_email(trim($email)) === false) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Validate IP Address
     *
     * @access  public
     * @param  string
     * @param  string "ipv4" or "ipv6" to validate a specific ip format
     * @return  string
     */
    public function valid_ip($ip, $which = '')
    {
        return $this->CI->input->valid_ip($ip, $which);
    }

    // --------------------------------------------------------------------

    /**
     * Alpha
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function alpha($str)
    {
        return (!preg_match("/^([a-z])+$/i", $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function alpha_numeric($str)
    {
        return (!preg_match("/^([a-z0-9])+$/i", $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Alpha-numeric with underscores and dashes
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function alpha_dash($str)
    {
        return (!preg_match("/^([-a-z0-9_-])+$/i", $str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Numeric
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function numeric($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]*\.?[0-9]+$/', $str);

    }

    // --------------------------------------------------------------------

    /**
     * Is Numeric
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function is_numeric($str)
    {
        return (!is_numeric($str)) ? false : true;
    }

    // --------------------------------------------------------------------

    /**
     * Integer
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function integer($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]+$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Decimal number
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function decimal($str)
    {
        return (bool)preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Greather than
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function greater_than($str, $min)
    {
        if (!is_numeric($str)) {
            return false;
        }
        return $str > $min;
    }

    // --------------------------------------------------------------------

    /**
     * Less than
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function less_than($str, $max)
    {
        if (!is_numeric($str)) {
            return false;
        }
        return $str < $max;
    }

    // --------------------------------------------------------------------

    /**
     * Is a Natural number  (0,1,2,3, etc.)
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function is_natural($str)
    {
        return (bool)preg_match('/^[0-9]+$/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Is a Natural number, but not a zero  (1,2,3, etc.)
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function is_natural_no_zero($str)
    {
        if (!preg_match('/^[0-9]+$/', $str)) {
            return false;
        }

        if ($str == 0) {
            return false;
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Valid Base64
     *
     * Tests a string for characters outside of the Base64 alphabet
     * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
     *
     * @access  public
     * @param  string
     * @return  bool
     */
    public function valid_base64($str)
    {
        return (bool)!preg_match('/[^a-zA-Z0-9\/\+=]/', $str);
    }

    // --------------------------------------------------------------------

    /**
     * Prep data for form
     *
     * This function allows HTML to be safely shown in a form.
     * Special characters are converted.
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function prep_for_form($data = '')
    {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->prep_for_form($val);
            }

            return $data;
        }

        if ($this->safe_form_data == false || $data === '') {
            return $data;
        }

        return str_replace(array("'", '"', '<', '>'), array("&#39;", "&quot;", '&lt;', '&gt;'), stripslashes($data));
    }

    // --------------------------------------------------------------------

    /**
     * Prep URL
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function prep_url($str = '')
    {
        if ($str == 'http://' || $str == '') {
            return '';
        }

        if (substr($str, 0, 7) != 'http://' && substr($str, 0, 8) != 'https://') {
            $str = 'http://' . $str;
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Strip Image Tags
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function strip_image_tags($str)
    {
        return $this->CI->input->strip_image_tags($str);
    }

    // --------------------------------------------------------------------

    /**
     * XSS Clean
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function xss_clean($str)
    {
        return $this->CI->security->xssClean($str);
    }

    // --------------------------------------------------------------------

    /**
     * Convert PHP tags to entities
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function encode_php_tags($str)
    {
        return str_replace(array('<?php', '<?PHP', '<?', '?>'), array('&lt;?php', '&lt;?PHP', '&lt;?', '?&gt;'), $str);
    }

}
// END Form Validation Class

/* End of file Form_validation.php */
/* Location: ./system/libraries/Form_validation.php */
