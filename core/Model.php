<?php
namespace CI\Core;

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * CodeIgniter Model Class
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/libraries/config.html
 *
 * @property \CI\Database\ActiveRecord                                                   $db
 * @property \CI\Database\Forge                                                          $dbforge
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
 * @property User_agent                                                            $userAgent
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
class Model
{
    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        log_message('debug', "Model Class Initialized");
    }

    /**
     * __get
     *
     * Allows models to access CI's loaded classes using the same
     * syntax as controllers.
     *
     * @param	string
     * @access private
     */
    public function __get($key)
    {
        $CI =& get_instance();
        return $CI->$key;
    }
}
// END Model Class

/* End of file Model.php */
/* Location: ./system/core/Model.php */
