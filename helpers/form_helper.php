<?php

namespace CI\Helpers;

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
     * CodeIgniter Form Helpers
     *
     * @package    CodeIgniter
     * @subpackage  Helpers
     * @category  Helpers
     * @author    ExpressionEngine Dev Team
     * @link    http://codeigniter.com/user_guide/helpers/form_helper.html
     */

// ------------------------------------------------------------------------


/**
 * Form Declaration
 *
 * Creates the opening portion of the form.
 *
 * @param  string       $action the URI segments of the form destination
 * @param  array|string $attributes a key/value pair of attributes
 * @param  array        $hidden a key/value pair hidden data
 * @return  string
 */
function form_open($action = '', $attributes = '', $hidden = array())
{
    $CI =& \CI\Core\get_instance();

    if ($attributes == '') {
        $attributes = 'method="post"';
    }

    // If an action is not a full URL then turn it into one
    if ($action && strpos($action, '://') === false) {
        $action = $CI->config->siteUrl($action);
    }

    // If no action is provided then set to the current url
    $action || $action = $CI->config->siteUrl($CI->uri->uriString());

    $form = '<form action="' . $action . '"';

    $form .= _attributes_to_string($attributes, true);

    $form .= '>';

    // Add CSRF field if enabled, but leave it out for GET requests and requests to external websites
    if ($CI->config->item('csrf_protection') === true &&
        !(strpos($action, $CI->config->baseUrl()) === false || strpos($form, 'method="get"'))
    ) {
        $hidden[$CI->security->getCsrfTokenName()] = $CI->security->getCsrfHash();
    }

    if (is_array($hidden) && count($hidden) > 0) {
        $form .= sprintf("<div style=\"display:none\">%s</div>", form_hidden($hidden));
    }

    return $form;
}

// ------------------------------------------------------------------------

/**
 * Form Declaration - Multipart type
 *
 * Creates the opening portion of the form, but with "multipart/form-data".
 *
 * @access  public
 * @param  string       $action the URI segments of the form destination
 * @param  array|string $attributes a key/value pair of attributes
 * @param  array        $hidden a key/value pair hidden data
 * @return  string
 */
function form_open_multipart($action = '', $attributes = array(), $hidden = array())
{
    if (is_string($attributes)) {
        $attributes .= ' enctype="multipart/form-data"';
    } else {
        $attributes['enctype'] = 'multipart/form-data';
    }

    return form_open($action, $attributes, $hidden);
}

// ------------------------------------------------------------------------

/**
 * Hidden Input Field
 *
 * Generates hidden fields.  You can pass a simple key/value string or an associative
 * array with multiple values.
 *
 * @access  public
 * @param mixed  $name
 * @param string $value
 * @param bool   $recursing
 * @return  string
 */
function form_hidden($name, $value = '', $recursing = false)
{
    static $form;

    if ($recursing === false) {
        $form = "\n";
    }

    if (is_array($name)) {
        foreach ($name as $key => $val) {
            form_hidden($key, $val, true);
        }
        return $form;
    }

    if (!is_array($value)) {
        $form .= '<input type="hidden" name="' . $name . '" value="' . form_prep($value, $name) . '" />' . "\n";
    } else {
        foreach ($value as $k => $v) {
            $k = (is_int($k)) ? '' : $k;
            form_hidden($name . '[' . $k . ']', $v, true);
        }
    }

    return $form;
}

// ------------------------------------------------------------------------

/**
 * Text Input Field
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  string $extra
 * @return  string
 */
function form_input($data = '', $value = '', $extra = '')
{
    $defaults = array('type' => 'text', 'name' => ((!is_array($data)) ? $data : ''), 'value' => $value);

    return "<input " . _parse_form_attributes($data, $defaults) . $extra . " />";
}

// ------------------------------------------------------------------------

/**
 * Password Field
 *
 * Identical to the input function but adds the "password" type
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  string $extra
 * @return  string
 */
function form_password($data = '', $value = '', $extra = '')
{
    if (!is_array($data)) {
        $data = array('name' => $data);
    }

    $data['type'] = 'password';
    return form_input($data, $value, $extra);
}

// ------------------------------------------------------------------------

/**
 * Upload Field
 *
 * Identical to the input function but adds the "file" type
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  string $extra
 * @return  string
 */
function form_upload($data = '', $value = '', $extra = '')
{
    if (!is_array($data)) {
        $data = array('name' => $data);
    }

    $data['type'] = 'file';
    return form_input($data, $value, $extra);
}

// ------------------------------------------------------------------------

/**
 * Textarea field
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  string $extra
 * @return  string
 */
function form_textarea($data = '', $value = '', $extra = '')
{
    $defaults = array('name' => ((!is_array($data)) ? $data : ''), 'cols' => '40', 'rows' => '10');

    if (!is_array($data) || !isset($data['value'])) {
        $val = $value;
    } else {
        $val = $data['value'];
        unset($data['value']); // textareas don't use the value attribute
    }

    $name = (is_array($data)) ? $data['name'] : $data;
    return "<textarea " . _parse_form_attributes($data, $defaults) . $extra . ">" . form_prep(
        $val,
        $name
    ) . "</textarea>";
}

// ------------------------------------------------------------------------

/**
 * Multi-select menu
 *
 * @access  public
 * @param  string $name
 * @param  array  $options
 * @param  mixed  $selected
 * @param  string $extra
 * @return  string
 */
function form_multiselect($name = '', $options = array(), $selected = array(), $extra = '')
{
    if (!strpos($extra, 'multiple')) {
        $extra .= ' multiple="multiple"';
    }

    return form_dropdown($name, $options, $selected, $extra);
}

// --------------------------------------------------------------------

/**
 * Drop-down Menu
 *
 * @access  public
 * @param  string       $name
 * @param  array        $options
 * @param  string|array $selected
 * @param  string       $extra
 * @return  string
 */
function form_dropdown($name = '', $options = array(), $selected = array(), $extra = '')
{
    if (!is_array($selected)) {
        $selected = array($selected);
    }

    // If no selected state was submitted we will attempt to set it automatically
    if (count($selected) === 0) {
        // If the form name appears in the $_POST array we have a winner!
        if (isset($_POST[$name])) {
            $selected = array($_POST[$name]);
        }
    }

    if ($extra != '') {
        $extra = ' ' . $extra;
    }

    $multiple = (count($selected) > 1 && strpos($extra, 'multiple') === false) ? ' multiple="multiple"' : '';

    $form = '<select namtypee="' . $name . '"' . $extra . $multiple . ">\n";

    foreach ($options as $key => $val) {
        $key = (string)$key;

        if (is_array($val) && !empty($val)) {
            $form .= '<optgroup label="' . $key . '">' . "\n";

            foreach ($val as $optgroup_key => $optgroup_val) {
                $sel = (in_array($optgroup_key, $selected)) ? ' selected="selected"' : '';

                $form .= '<option value="' . $optgroup_key . '"' . $sel . '>' . (string)$optgroup_val . "</option>\n";
            }

            $form .= '</optgroup>' . "\n";
        } else {
            $sel = (in_array($key, $selected)) ? ' selected="selected"' : '';

            $form .= '<option value="' . $key . '"' . $sel . '>' . (string)$val . "</option>\n";
        }
    }

    $form .= '</select>';

    return $form;
}

// ------------------------------------------------------------------------

/**
 * Checkbox Field
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  bool   $checked
 * @param  string $extra
 * @return  string
 */
function form_checkbox($data = '', $value = '', $checked = false, $extra = '')
{
    $defaults = array('type' => 'checkbox', 'name' => ((!is_array($data)) ? $data : ''), 'value' => $value);

    if (is_array($data) && array_key_exists('checked', $data)) {
        $checked = $data['checked'];

        if ($checked == false) {
            unset($data['checked']);
        } else {
            $data['checked'] = 'checked';
        }
    }

    if ($checked == true) {
        $defaults['checked'] = 'checked';
    } else {
        unset($defaults['checked']);
    }

    return "<input " . _parse_form_attributes($data, $defaults) . $extra . " />";
}

// ------------------------------------------------------------------------

/**
 * Radio Button
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  bool   $checked
 * @param  string $extra
 * @return  string
 */
function form_radio($data = '', $value = '', $checked = false, $extra = '')
{
    if (!is_array($data)) {
        $data = array('name' => $data);
    }

    $data['type'] = 'radio';
    return form_checkbox($data, $value, $checked, $extra);
}

// ------------------------------------------------------------------------

/**
 * Submit Button
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  string $extra
 * @return  string
 */
function form_submit($data = '', $value = '', $extra = '')
{
    $defaults = array('type' => 'submit', 'name' => ((!is_array($data)) ? $data : ''), 'value' => $value);

    return "<input " . _parse_form_attributes($data, $defaults) . $extra . " />";
}

// ------------------------------------------------------------------------

/**
 * Reset Button
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $value
 * @param  string $extra
 * @return  string
 */
function form_reset($data = '', $value = '', $extra = '')
{
    $defaults = array('type' => 'reset', 'name' => ((!is_array($data)) ? $data : ''), 'value' => $value);

    return "<input " . _parse_form_attributes($data, $defaults) . $extra . " />";
}

// ------------------------------------------------------------------------

/**
 * Form Button
 *
 * @access  public
 * @param  mixed  $data
 * @param  string $content
 * @param  string $extra
 * @return  string
 */

function form_button($data = '', $content = '', $extra = '')
{
    $defaults = array('name' => ((!is_array($data)) ? $data : ''), 'type' => 'button');

    if (is_array($data) && isset($data['content'])) {
        $content = $data['content'];
        unset($data['content']); // content is not an attribute
    }

    return "<button " . _parse_form_attributes($data, $defaults) . $extra . ">" . $content . "</button>";
}

// ------------------------------------------------------------------------

/**
 * Form Label Tag
 *
 * @access  public
 * @param  string       $label_text The text to appear onscreen
 * @param  string       $id The id the label applies to
 * @param  array|string $attributes Additional attributes
 * @return  string
 */

function form_label($label_text = '', $id = '', $attributes = array())
{

    $label = '<label';

    if ($id != '') {
        $label .= " for=\"$id\"";
    }

    if (is_array($attributes) && count($attributes) > 0) {
        foreach ($attributes as $key => $val) {
            $label .= ' ' . $key . '="' . $val . '"';
        }
    }

    $label .= ">$label_text</label>";

    return $label;
}

// ------------------------------------------------------------------------
/**
 * Fieldset Tag
 *
 * Used to produce <fieldset><legend>text</legend>.  To close fieldset
 * use form_fieldset_close()
 *
 * @access
 * @param  string       $legend_text The legend text
 * @param  array|string $attributes Additional attributes
 * @return  string
 */
function form_fieldset($legend_text = '', $attributes = array())
{
    $fieldset = "<fieldset";

    $fieldset .= _attributes_to_string($attributes, false);

    $fieldset .= ">\n";

    if ($legend_text != '') {
        $fieldset .= "<legend>$legend_text</legend>\n";
    }

    return $fieldset;
}

// ------------------------------------------------------------------------

/**
 * Fieldset Close Tag
 *
 * @access  public
 * @param  string $extra
 * @return  string
 */


function form_fieldset_close($extra = '')
{
    return "</fieldset>" . $extra;
}

// ------------------------------------------------------------------------

/**
 * Form Close Tag
 *
 * @access  public
 * @param  string $extra
 * @return  string
 */

function form_close($extra = '')
{
    return "</form>" . $extra;
}

// ------------------------------------------------------------------------

/**
 * Form Prep
 *
 * Formats text so that it can be safely placed in a form field in the event it has HTML tags.
 *
 * @access  public
 * @param string $str
 * @param string $field_name
 * @return  string
 */

function form_prep($str = '', $field_name = '')
{
    static $prepped_fields = array();

    // if the field name is an array we do this recursively
    if (is_array($str)) {
        foreach ($str as $key => $val) {
            $str[$key] = form_prep($val);
        }

        return $str;
    }

    if ($str === '') {
        return '';
    }

    // we've already prepped a field with this name
    // @todo need to figure out a way to namespace this so
    // that we know the *exact* field and not just one with
    // the same name
    if (isset($prepped_fields[$field_name])) {
        return $str;
    }

    $str = htmlspecialchars($str);

    // In case htmlspecialchars misses these.
    $str = str_replace(array("'", '"'), array("&#39;", "&quot;"), $str);

    if ($field_name != '') {
        $prepped_fields[$field_name] = $field_name;
    }

    return $str;
}

// ------------------------------------------------------------------------

/**
 * Form Value
 *
 * Grabs a value from the POST array for the specified field so you can
 * re-populate an input field or textarea.  If Form Validation
 * is active it retrieves the info from the validation class
 *
 * @access  public
 * @param string $field
 * @param string $default
 * @return  mixed
 */

function set_value($field = '', $default = '')
{
    if (false === ($OBJ =& _get_validation_object())) {
        if (!isset($_POST[$field])) {
            return $default;
        }

        return form_prep($_POST[$field], $field);
    }

    return form_prep($OBJ->setValue($field, $default), $field);
}

// ------------------------------------------------------------------------

/**
 * Set Select
 *
 * Let's you set the selected value of a <select> menu via data in the POST array.
 * If Form Validation is active it retrieves the info from the validation class
 *
 * @access  public
 * @param  string $field
 * @param  string $value
 * @param  bool   $default
 * @return  string
 */

function set_select($field = '', $value = '', $default = false)
{
    $OBJ =& _get_validation_object();

    if ($OBJ === false) {
        if (!isset($_POST[$field])) {
            if (count($_POST) === 0 && $default == true) {
                return ' selected="selected"';
            }
            return '';
        }

        $field = $_POST[$field];

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

    return $OBJ->setSelect($field, $value, $default);
}

// ------------------------------------------------------------------------

/**
 * Set Checkbox
 *
 * Let's you set the selected value of a checkbox via the value in the POST array.
 * If Form Validation is active it retrieves the info from the validation class
 *
 * @access  public
 * @param  string $field
 * @param  string $value
 * @param  bool   $default
 * @return  string
 */

function set_checkbox($field = '', $value = '', $default = false)
{
    $OBJ =& _get_validation_object();

    if ($OBJ === false) {
        if (!isset($_POST[$field])) {
            if (count($_POST) === 0 && $default == true) {
                return ' checked="checked"';
            }
            return '';
        }

        $field = $_POST[$field];

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

    return $OBJ->setCheckbox($field, $value, $default);
}

// ------------------------------------------------------------------------

/**
 * Set Radio
 *
 * Let's you set the selected value of a radio field via info in the POST array.
 * If Form Validation is active it retrieves the info from the validation class
 *
 * @access  public
 * @param  string $field
 * @param  string $value
 * @param  bool   $default
 * @return  string
 */

function set_radio($field = '', $value = '', $default = false)
{
    $OBJ =& _get_validation_object();

    if ($OBJ === false) {
        if (!isset($_POST[$field])) {
            if (count($_POST) === 0 && $default == true) {
                return ' checked="checked"';
            }
            return '';
        }

        $field = $_POST[$field];

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

    return $OBJ->setRadio($field, $value, $default);
}

// ------------------------------------------------------------------------

/**
 * Form Error
 *
 * Returns the error for a specific form field.  This is a helper for the
 * form validation class.
 *
 * @access  public
 * @param  string $field
 * @param  string $prefix
 * @param  string $suffix
 * @return  string
 */

function form_error($field = '', $prefix = '', $suffix = '')
{
    if (false === ($OBJ =& _get_validation_object())) {
        return '';
    }

    return $OBJ->error($field, $prefix, $suffix);
}

// ------------------------------------------------------------------------

/**
 * Validation Error String
 *
 * Returns all the errors associated with a form submission.  This is a helper
 * function for the form validation class.
 *
 * @access  public
 * @param  string $prefix
 * @param  string $suffix
 * @return  string
 */

function validation_errors($prefix = '', $suffix = '')
{
    if (false === ($OBJ =& _get_validation_object())) {
        return '';
    }

    return $OBJ->errorString($prefix, $suffix);
}

// ------------------------------------------------------------------------

/**
 * Parse the form attributes
 *
 * Helper function used by some of the form helpers
 *
 * @access  private
 * @param  array $attributes
 * @param  array $default
 * @return  string
 */

function _parse_form_attributes($attributes, $default)
{
    if (is_array($attributes)) {
        foreach ($default as $key => $val) {
            if (isset($attributes[$key])) {
                $default[$key] = $attributes[$key];
                unset($attributes[$key]);
            }
        }

        if (count($attributes) > 0) {
            $default = array_merge($default, $attributes);
        }
    }

    $att = '';

    foreach ($default as $key => $val) {
        if ($key == 'value') {
            $val = form_prep($val, $default['name']);
        }

        $att .= $key . '="' . $val . '" ';
    }

    return $att;
}

// ------------------------------------------------------------------------

/**
 * Attributes To String
 *
 * Helper function used by some of the form helpers
 *
 * @access  private
 * @param  mixed $attributes
 * @param  bool  $formtag
 * @return  string
 */

function _attributes_to_string($attributes, $formtag = false)
{
    if (is_string($attributes) && strlen($attributes) > 0) {
        if ($formtag == true && strpos($attributes, 'method=') === false) {
            $attributes .= ' method="post"';
        }

        if ($formtag == true && strpos($attributes, 'accept-charset=') === false) {
            $attributes .= ' accept-charset="' . strtolower(\CI\Core\config_item('charset')) . '"';
        }

        return ' ' . $attributes;
    }

    if (is_object($attributes) && count($attributes) > 0) {
        $attributes = (array)$attributes;
    }

    if (is_array($attributes) && count($attributes) > 0) {
        $atts = '';

        if (!isset($attributes['method']) && $formtag === true) {
            $atts .= ' method="post"';
        }

        if (!isset($attributes['accept-charset']) && $formtag === true) {
            $atts .= ' accept-charset="' . strtolower(\CI\Core\config_item('charset')) . '"';
        }

        foreach ($attributes as $key => $val) {
            $atts .= ' ' . $key . '="' . $val . '"';
        }

        return $atts;
    }

    return '';
}

// ------------------------------------------------------------------------

/**
 * Validation Object
 *
 * Determines what the form validation class was instantiated as, fetches
 * the object and returns it.
 *
 * @access  private
 * @return  \CI\Libraries\FormValidation
 */
function &_get_validation_object()
{
    $CI =& \CI\Core\get_instance();

    // We set this as a variable since we're returning by reference.
    $return = false;

    if (false !== ($object = $CI->load->isLoaded('formvalidation'))) {
        if (!isset($CI->$object) || !is_object($CI->$object)) {
            return $return;
        }

        return $CI->$object;
    }

    return $return;
}


/* End of file form_helper.php */
/* Location: ./system/helpers/form_helper.php */
