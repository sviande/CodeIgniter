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
 * CodeIgniter Application Controller Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Libraries
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/general/controllers.html
 *
 * @property \CI\DB\ActiveRecord                                                   $db
 * @property \CI\DB\Forge                                                          $dbforge
 * @property Benchmark                                                             $benchmark
 * @property \CI\Libraries\Calendar                                                $calendar
 * @property \CI\Libraries\Cart                                                    $cart
 * @property Config                                                                $config
 * @property Controller                                                            $controller
 * @property \CI\Libraries\Email                                                   $email
 * @property Encrypt                                                               $encrypt
 * @property Exceptions                                                            $exceptions
 * @property \CI\Libraries\FormValidation                                          $formvalidation
 * @property Ftp                                                                   $ftp
 * @property Hooks                                                                 $hooks
 * @property Image_lib                                                             $image_lib
 * @property Input                                                                 $input
 * @property Lang                                                                  $lang
 * @property Loader                                                                $load
 * @property Log                                                                   $log
 * @property Model                                                                 $model
 * @property Output                                                                $output
 * @property Pagination                                                            $pagination
 * @property Parser                                                                $parser
 * @property Profiler                                                              $profiler
 * @property Router                                                                $router
 * @property \CI\Libraries\Session                                                 $session
 * @property Sha1                                                                  $sha1
 * @property Table                                                                 $table
 * @property Trackback                                                             $trackback
 * @property Typography                                                            $typography
 * @property Unit_test                                                             $unit_test
 * @property Upload                                                                $upload
 * @property URI                                                                   $uri
 * @property User_agent                                                            $user_agent
 * @property Validation                                                            $validation
 * @property Xmlrpc                                                                $xmlrpc
 * @property Xmlrpcs                                                               $xmlrpcs
 * @property Zip                                                                   $zip
 * @property Javascript                                                            $javascript
 * @property Jquery                                                                $jquery
 * @property Utf8                                                                  $utf8
 * @property Security                                                              $security
 * @property Driver_Library                                                        $driver
 * @property Cache                                                                 $cache
 */
class Controller
{

    private static $instance;

    /**
     * Constructor
     */
    public function __construct()
    {
        self::$instance =& $this;

        // Assign all the class objects that were instantiated by the
        // bootstrap file (CodeIgniter.php) to local class variables
        // so that CI can run as one big super object.
        foreach (is_loaded() as $var => $class) {
            $this->$var =& load_class($class);
        }

        $this->load =& load_class('Loader', 'core');

        $this->load->initialize();

        log_message('debug', "Controller Class Initialized");
    }

    public static function &getInstance()
    {
        return self::$instance;
    }
}
// END Controller class

/* End of file Controller.php */
/* Location: ./system/core/Controller.php */
