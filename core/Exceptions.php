<?php
namespace CI\Core;

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
 * Exceptions Class
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Exceptions
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/exceptions.html
 */
class Exceptions
{
    public $action;
    public $severity;
    public $message;
    public $filename;
    public $line;

    /**
     * Nesting level of the output buffering mechanism
     *
     * @var int
     * @access public
     */
    public $ob_level;

    /**
     * List if available error levels
     *
     * @var array
     * @access public
     */
    public $levels = array(
        E_ERROR           => 'Error',
        E_WARNING         => 'Warning',
        E_PARSE           => 'Parsing Error',
        E_NOTICE          => 'Notice',
        E_CORE_ERROR      => 'Core Error',
        E_CORE_WARNING    => 'Core Warning',
        E_COMPILE_ERROR   => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR      => 'User Error',
        E_USER_WARNING    => 'User Warning',
        E_USER_NOTICE     => 'User Notice',
        E_STRICT          => 'Runtime Notice'
    );


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ob_level = ob_get_level();
        // Note:  Do not log messages from this constructor.
    }

    // --------------------------------------------------------------------

    /**
     * Exception Logger
     *
     * This function logs PHP generated error messages
     *
     * @access  private
     * @param  string $severity the error severity
     * @param  string $message the error string
     * @param  string $filepath the error filepath
     * @param  string $line the error line number
     * @return  string
     */
    public function logException($severity, $message, $filepath, $line)
    {
        $severity = (!isset($this->levels[$severity])) ? $severity : $this->levels[$severity];

        log_message('error', 'Severity: ' . $severity . '  --> ' . $message . ' ' . $filepath . ' ' . $line, true);
    }

    // --------------------------------------------------------------------

    /**
     * 404 Page Not Found Handler
     *
     * @param  string $page the page
     * @param  bool   $log_error log error yes/no
     * @return  string
     */
    public function show404($page = '', $log_error = true)
    {
        $heading = "404 Page Not Found";
        $message = "The page you requested was not found.";

        // By default we log this, but allow a dev to skip it
        if ($log_error) {
            log_message('error', '404 Page Not Found --> ' . $page);
        }

        echo $this->showError($heading, $message, 'error_404', 404);
        exit;
    }

    // --------------------------------------------------------------------

    /**
     * General Error Page
     *
     * This function takes an error message as input
     * (either as a string or an array) and displays
     * it using the specified template.
     *
     * @access  private
     * @param  string $heading the heading
     * @param  string $message the message
     * @param  string $template the template name
     * @param  int    $status_code the status code
     * @return  string
     */
    public function showError($heading, $message, $template = 'error_general', $status_code = 500)
    {
        set_status_header($status_code);

        $message = '<p>' . implode('</p><p>', (!is_array($message)) ? array($message) : $message) . '</p>';

        if (ob_get_level() > $this->ob_level + 1) {
            ob_end_flush();
        }
        ob_start();
        include(APPPATH . 'errors/' . $template . '.php');
        $buffer = ob_get_contents();
        ob_end_clean();
        return $buffer;
    }

    // --------------------------------------------------------------------

    /**
     * Native PHP error handler
     *
     * @access  private
     * @param  string $severity the error severity
     * @param  string $message the error string
     * @param  string $filepath the error filepath
     * @param  string $line the error line number
     * @return  string
     */
    public function showPhpError($severity, $message, $filepath, $line)
    {
        $severity = (!isset($this->levels[$severity])) ? $severity : $this->levels[$severity];

        $filepath = str_replace("\\", "/", $filepath);

        // For safety reasons we do not show the full file path
        if (false !== strpos($filepath, '/')) {
            $x = explode('/', $filepath);
            $filepath = $x[count($x) - 2] . '/' . end($x);
        }

        if (ob_get_level() > $this->ob_level + 1) {
            ob_end_flush();
        }
        ob_start();
        include(APPPATH . 'errors/error_php.php');
        $buffer = ob_get_contents();
        ob_end_clean();
        echo $buffer;
    }
}
// END Exceptions Class

/* End of file Exceptions.php */
/* Location: ./system/core/Exceptions.php */
