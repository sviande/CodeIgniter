<?php
namespace CI\Libraries;

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
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
 * Logging Class
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Logging
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/general/errors.html
 */
class Log
{
    protected $log_path;
    protected $threshold = 1;
    protected $date_fmt = 'Y-m-d H:i:s';
    protected $enabled = true;
    protected $levels = array('ERROR' => '1', 'DEBUG' => '2', 'INFO' => '3', 'ALL' => '4');

    /**
     * Constructor
     */
    public function __construct()
    {
        $config =& \CI\Core\get_config();

        $this->log_path = ($config['log_path'] != '') ? $config['log_path'] : APPPATH . 'logs/';

        if (!is_dir($this->log_path) || !\CI\Core\is_really_writable($this->log_path)) {
            $this->enabled = false;
        }

        if (is_numeric($config['log_threshold'])) {
            $this->threshold = $config['log_threshold'];
        }

        if ($config['log_date_format'] != '') {
            $this->date_fmt = $config['log_date_format'];
        }
    }

    // --------------------------------------------------------------------

    /**
     * Write Log File
     *
     * Generally this function will be called using the global log_message() function
     *
     * @param  string $level the error level
     * @param  string $msg the error message
     *
     * @return  bool
     */
    public function writeLog($level = 'error', $msg = '')
    {
        if ($this->enabled === false) {
            return false;
        }

        $level = strtoupper($level);

        if (!isset($this->levels[$level]) || ($this->levels[$level] > $this->threshold)) {
            return false;
        }

        $filepath = $this->log_path . 'log-' . date('Y-m-d') . '.php';
        $message  = '';

        if (!file_exists($filepath)) {
            $message .= "<" . "?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?" . ">\n\n";
        }

        if (!$fp = @fopen($filepath, FOPEN_WRITE_CREATE)) {
            return false;
        }

        $message .= $level . ' ' . (($level == 'INFO') ? ' -' : '-') . ' ' .
            date(
                $this->date_fmt
            ) . ' --> ' . $msg . "\n";

        flock($fp, LOCK_EX);
        fwrite($fp, $message);
        flock($fp, LOCK_UN);
        fclose($fp);

        @chmod($filepath, FILE_WRITE_MODE);
        return true;
    }

}
// END Log Class

/* End of file Log.php */
/* Location: ./system/libraries/Log.php */
