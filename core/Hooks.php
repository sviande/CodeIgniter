<?php
namespace CI\core;

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
 * CodeIgniter Hooks Class
 *
 * Provides a mechanism to extend the base system without hacking.
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Libraries
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/encryption.html
 */
class Hooks
{

    /**
     * Determines wether hooks are enabled
     *
     * @var bool
     */
    public $enabled = false;
    /**
     * List of all hooks set in config/hooks.php
     *
     * @var array
     */
    public $hooks = array();
    /**
     * Determines wether hook is in progress, used to prevent infinte loops
     *
     * @var bool
     */
    public $in_progress = false;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->initialize();
        log_message('debug', "Hooks Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Initialize the Hooks Preferences
     *
     * @access  private
     * @return  void
     */
    private function initialize()
    {
        $CFG =& load_class('Config', 'core');

        // If hooks are not enabled in the config file
        // there is nothing else to do

        if ($CFG->item('enable_hooks') == false) {
            return;
        }

        // Grab the "hooks" definition file.
        // If there are no hooks, we're done.

        if (defined('ENVIRONMENT') && is_file(APPPATH . 'config/' . ENVIRONMENT . '/hooks.php')) {
            include(APPPATH . 'config/' . ENVIRONMENT . '/hooks.php');
        } elseif (is_file(APPPATH . 'config/hooks.php')) {
            include(APPPATH . 'config/hooks.php');
        }


        if (!isset($hook) || !is_array($hook)) {
            return;
        }

        $this->hooks   =& $hook;
        $this->enabled = true;
    }

    // --------------------------------------------------------------------

    /**
     * Call Hook
     *
     * Calls a particular hook
     *
     * @access  private
     * @param  string $which the hook name
     * @return  mixed
     */
    public function callHook($which = '')
    {
        if (!$this->enabled || !isset($this->hooks[$which])) {
            return false;
        }

        if (isset($this->hooks[$which][0]) && is_array($this->hooks[$which][0])) {
            foreach ($this->hooks[$which] as $val) {
                $this->runHook($val);
            }
        } else {
            $this->runHook($this->hooks[$which]);
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Run Hook
     *
     * Runs a particular hook
     *
     * @access  private
     * @param  array $data the hook details
     * @return  bool
     */
    private function runHook($data)
    {
        if (!is_array($data)) {
            return false;
        }

        // -----------------------------------
        // Safety - Prevents run-away loops
        // -----------------------------------

        // If the script being called happens to have the same
        // hook call within it a loop can happen

        if ($this->in_progress == true) {
            return true;
        }

        // -----------------------------------
        // Set file path
        // -----------------------------------

        if (!isset($data['filepath']) || !isset($data['filename'])) {
            return false;
        }

        $filepath = APPPATH . $data['filepath'] . '/' . $data['filename'];

        if (!file_exists($filepath)) {
            return false;
        }

        // -----------------------------------
        // Set class/function name
        // -----------------------------------

        $params = '';

        if ((!isset($data['class']) || $data['class'] == '') || !isset($data['function'])) {
            return false;
        }

        $class    = $data['class'] ? $data['class'] : false;
        $function = $data['function'];

        if (isset($data['params'])) {
            $params = $data['params'];
        }

        // -----------------------------------
        // Set the in_progress flag
        // -----------------------------------

        $this->in_progress = true;

        // -----------------------------------
        // Call the requested class and/or function
        // -----------------------------------

        if ($class !== false) {
            if (!class_exists($class, false)) {
                require($filepath);
            }

            $HOOK = new $class;
            $HOOK->$function($params);
        } else {
            if (!function_exists($function)) {
                require($filepath);
            }

            $function($params);
        }

        $this->in_progress = false;
        return true;
    }
}

// END CI\Core\Hooks class

/* End of file Hooks.php */
/* Location: ./system/core/Hooks.php */
