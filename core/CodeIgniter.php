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
     * System Initialization File
     *
     * Loads the base classes and executes the request.
     *
     * @package    CodeIgniter
     * @subpackage  codeigniter
     * @category  Front-controller
     * @author    ExpressionEngine Dev Team
     * @link    http://codeigniter.com/user_guide/
     */

/**
 * CodeIgniter Version
 *
 * @var string
 *
 */
define('CI_VERSION', '2.1.4');

/**
 * CodeIgniter Branch (Core = TRUE, Reactor = FALSE)
 *
 * @var boolean
 *
 */
define('CI_CORE', false);


/*
 * ------------------------------------------------------
 *  Load the global functions
 * ------------------------------------------------------
 */
require(BASEPATH . 'core/Common.php');

/*
 * ------------------------------------------------------
 *  Load the framework constants
 * ------------------------------------------------------
 */
if (defined('ENVIRONMENT') && file_exists(APPPATH . 'config/' . ENVIRONMENT . '/constants.php')) {
    require(APPPATH . 'config/' . ENVIRONMENT . '/constants.php');
} else {
    require(APPPATH . 'config/constants.php');
}

/*
 * ------------------------------------------------------
 *  Define a custom error handler so we can log PHP errors
 * ------------------------------------------------------
 */
set_error_handler('\CI\Core\_exception_handler');


/*
 * ------------------------------------------------------
 *  Set the subclass_prefix
 * ------------------------------------------------------
 *
 * Normally the "subclass_prefix" is set in the config file.
 * The subclass prefix allows CI to know if a core class is
 * being extended via a library in the local application
 * "libraries" folder. Since CI allows config items to be
 * overriden via data set in the main index. php file,
 * before proceeding we need to know if a subclass_prefix
 * override exists.  If so, we will set this value now,
 * before any classes are loaded
 * Note: Since the config file data is cached it doesn't
 * hurt to load it here.
 */
if (isset($assign_to_config['subclass_prefix']) && $assign_to_config['subclass_prefix'] != '') {
    get_config(array('subclass_prefix' => $assign_to_config['subclass_prefix']));
}

/*
 * ------------------------------------------------------
 *  Set a liberal script execution time limit
 * ------------------------------------------------------
 */
if (function_exists("set_time_limit") == true && @ini_get("safe_mode") == 0) {
    @set_time_limit(300);
}

/**
 * ------------------------------------------------------
 *  Start the timer... tick tock tick tock...
 * ------------------------------------------------------
 * @var Benchmark $BM
 */
$BM =& load_class('Benchmark', 'core');
$BM->mark('total_execution_time_start');
$BM->mark('loading_time:_base_classes_start');

/**
 * ------------------------------------------------------
 *  Instantiate the hooks class
 * ------------------------------------------------------
 * @var \CI\Core\Hooks $EXT
 */
$EXT =& load_class('Hooks', 'core');

/*
 * ------------------------------------------------------
 *  Is there a "pre_system" hook?
 * ------------------------------------------------------
 */
$EXT->callHook('pre_system');

/**
 * ------------------------------------------------------
 *  Instantiate the config class
 * ------------------------------------------------------
 * @var \CI\Core\Config $CFG
 */
$CFG =& load_class('Config', 'core');

// Do we have any manually set config items in the index.php file?
if (isset($assign_to_config)) {
    $CFG->assignToConfig($assign_to_config);
}

/**
 * ------------------------------------------------------
 *  Instantiate the UTF-8 class
 * ------------------------------------------------------
 *
 * Note: Order here is rather important as the UTF-8
 * class needs to be used very early on, but it cannot
 * properly determine if UTf-8 can be supported until
 * after the Config class is instantiated.
 *
 * @var \CI\Core\Utf8 $UNI
 */

$UNI =& load_class('Utf8', 'core');

/**
 * ------------------------------------------------------
 *  Instantiate the URI class
 * ------------------------------------------------------
 * @var \CI\Core\URI $URI
 */
$URI =& load_class('URI', 'core');

/**
 * ------------------------------------------------------
 *  Instantiate the routing class and set the routing
 * ------------------------------------------------------
 * @var \CI\Core\Router $RTR
 */
$RTR =& load_class('Router', 'core');
$RTR->setRouting();

// Set any routing overrides that may exist in the main index file
if (isset($routing)) {
    $RTR->setOverrides($routing);
}

/**
 * ------------------------------------------------------
 *  Instantiate the output class
 * ------------------------------------------------------
 * @var \CI\Core\Output $OUT
 */
$OUT =& load_class('Output', 'core');

/*
 * ------------------------------------------------------
 *	Is there a valid cache file?  If so, we're done...
 * ------------------------------------------------------
 */
if ($EXT->callHook('cache_override') === false) {
    if ($OUT->displayCache($CFG, $URI) == true) {
        exit;
    }
}

/**
 * -----------------------------------------------------
 * Load the security class for xss and csrf support
 * -----------------------------------------------------
 * @var \CI\Core\Security $SEC
 */
$SEC =& load_class('Security', 'core');

/**
 * ------------------------------------------------------
 *  Load the Input class and sanitize globals
 * ------------------------------------------------------
 * @var \CI\Core\Input $IN
 */
$IN =& load_class('Input', 'core');

/**
 * ------------------------------------------------------
 *  Load the Language class
 * ------------------------------------------------------
 * @var \CI\Core\Lang $LANG
 */
$LANG =& load_class('Lang', 'core');

/**
 * ------------------------------------------------------
 *  Load the app controller and local controller
 * ------------------------------------------------------
 *
 */
// Load the base controller class
require BASEPATH . 'core/Controller.php';

function &get_instance()
{
    return Controller::getInstance();
}


if (file_exists(APPPATH . 'core/Controller.php')) {
    require APPPATH . 'core/Controller.php';
}

// Load the local application controller
// Note: The Router class automatically validates the controller path using the router->validateRequest().
// If this include fails it means that the default controller in the Routes.php file is not resolving to something valid
if (!file_exists(APPPATH . 'controllers/' . $RTR->fetchDirectory() . $RTR->fetchFile() . '.php')) {
    show_error(
        'Unable to load your default controller.'
    );
}

include(APPPATH . 'controllers/' . $RTR->fetchDirectory() . $RTR->fetchFile() . '.php');

// Set a mark point for benchmarking
$BM->mark('loading_time:_base_classes_end');

/*
 * ------------------------------------------------------
 *  Security check
 * ------------------------------------------------------
 *
 *  None of the functions in the app controller or the
 *  loader class can be called via the URI, nor can
 *  controller functions that begin with an underscore
 */
$class  = $RTR->fetchClass();
$method = $RTR->fetchMethod();
if (!class_exists($class, false)
    || strncmp($method, '_', 1) == 0
    || !in_array(strtolower($method), array_map('strtolower', get_class_methods($class)))
) {
    if (!empty($RTR->routes['404_override'])) {
        $x      = explode('/', $RTR->routes['404_override']);
        $class  = $x[0];
        $method = (isset($x[1]) ? $x[1] : 'index');
        if (!class_exists($class, false)) {
            if (!file_exists(APPPATH . 'controllers/' . $class . '.php')) {
                show_404("{$class}/{$method}");
            }

            include_once(APPPATH . 'controllers/' . $class . '.php');
        }
    } else {
        show_404("{$class}/{$method}");
    }
}

/*
 * ------------------------------------------------------
 *  Is there a "pre_controller" hook?
 * ------------------------------------------------------
 */
$EXT->callHook('pre_controller');

/*
 * ------------------------------------------------------
 *  Instantiate the requested controller
 * ------------------------------------------------------
 */
// Mark a start point so we can benchmark the controller
$BM->mark('controller_execution_time_( ' . $class . ' / ' . $method . ' )_start');

$CI = new $class();

/*
 * ------------------------------------------------------
 *  Is there a "post_controller_constructor" hook?
 * ------------------------------------------------------
 */
$EXT->callHook('post_controller_constructor');

/*
 * ------------------------------------------------------
 *  Call the requested method
 * ------------------------------------------------------
 */
// Is there a "remap" function? If so, we call it instead
if (method_exists($CI, '_remap')) {
    $CI->_remap($method, array_slice($URI->rsegments, 2));
} else {
    // is_callable() returns TRUE on some versions of PHP 5 for private and protected
    // methods, so we'll use this workaround for consistent behavior
    if (!in_array(strtolower($method), array_map('strtolower', get_class_methods($CI)))) {
        // Check and see if we are using a 404 override and use it.
        if (!empty($RTR->routes['404_override'])) {
            $x      = explode('/', $RTR->routes['404_override']);
            $class  = $x[0];
            $method = (isset($x[1]) ? $x[1] : 'index');
            if (!class_exists($class, false)) {
                if (!file_exists(APPPATH . 'controllers/' . $class . '.php')) {
                    show_404("{$class}/{$method}");
                }

                include_once(APPPATH . 'controllers/' . $class . '.php');
                unset($CI);
                $CI = new $class();
            }
        } else {
            show_404("{$class}/{$method}");
        }
    }

    // Call the requested method.
    // Any URI segments present (besides the class/function) will be passed to the method for convenience
    call_user_func_array(array(&$CI, $method), array_slice($URI->rsegments, 2));
}


// Mark a benchmark end point
$BM->mark('controller_execution_time_( ' . $class . ' / ' . $method . ' )_end');

/*
 * ------------------------------------------------------
 *  Is there a "post_controller" hook?
 * ------------------------------------------------------
 */
$EXT->callHook('post_controller');

if (class_exists('CI\Libraries\Session', false) && isset($CI->session)) {
    /** @var $CI Controller */
    $CI->session->close();
}

if (class_exists('CI\DB\ActiveRecord', false) && isset($CI->db)) {
    $CI->db->close();
}

if ($EXT->callHook('display_override') === false) {
    $OUT->display();
}

/*
 * ------------------------------------------------------
 *  Is there a "post_system" hook?
 * ------------------------------------------------------
 */
$EXT->callHook('post_system');




/* End of file CodeIgniter.php */
/* Location: ./system/core/CodeIgniter.php */
