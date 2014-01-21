<?php
namespace CI;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
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
 * CodeIgniter Application Controller Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		ExpressionEngine Dev Team
 * @link		http://codeigniter.com/user_guide/general/controllers.html
 *
 * @property DB_active_record $db              This is the platform-independent base Active Record implementation class.
 * @property DB_forge $dbforge                 Database Utility Class
 * @property Benchmark $benchmark              This class enables you to mark points and calculate the time difference between them.<br />  Memory consumption can also be displayed.
 * @property Calendar $calendar                This class enables the creation of calendars
 * @property Cart $cart                        Shopping Cart Class
 * @property Config $config                    This class contains functions that enable config files to be managed
 * @property Controller $controller            This class object is the super class that every library in.<br />CodeIgniter will be assigned to.
 * @property Email $email                      Permits email to be sent using Mail, Sendmail, or SMTP.
 * @property Encrypt $encrypt                  Provides two-way keyed encoding using XOR Hashing and Mcrypt
 * @property Exceptions $exceptions            Exceptions Class
 * @property Form_validation $form_validation  Form Validation Class
 * @property Ftp $ftp                          FTP Class
 * @property Hooks $hooks                      //dead
 * @property Image_lib $image_lib              Image Manipulation class
 * @property Input $input                      Pre-processes global input data for security
 * @property Lang $lang                        Language Class
 * @property Loader $load                      Loads views and files
 * @property Log $log                          Logging Class
 * @property Model $model                      CodeIgniter Model Class
 * @property Output $output                    Responsible for sending final output to browser
 * @property Pagination $pagination            Pagination Class
 * @property Parser $parser                    Parses pseudo-variables contained in the specified template view,<br />replacing them with the data in the second param
 * @property Profiler $profiler                This class enables you to display benchmark, query, and other data<br />in order to help with debugging and optimization.
 * @property Router $router                    Parses URIs and determines routing
 * @property Session $session                  Session Class
 * @property Sha1 $sha1                        Provides 160 bit hashing using The Secure Hash Algorithm
 * @property Table $table                      HTML table generation<br />Lets you create tables manually or from database result objects, or arrays.
 * @property Trackback $trackback              Trackback Sending/Receiving Class
 * @property Typography $typography            Typography Class
 * @property Unit_test $unit_test              Simple testing class
 * @property Upload $upload                    File Uploading Class
 * @property URI $uri                          Parses URIs and determines routing
 * @property User_agent $user_agent            Identifies the platform, browser, robot, or mobile devise of the browsing agent
 * @property Validation $validation            //dead
 * @property Xmlrpc $xmlrpc                    XML-RPC request handler class
 * @property Xmlrpcs $xmlrpcs                  XML-RPC server class
 * @property Zip $zip                          Zip Compression Class
 * @property Javascript $javascript            Javascript Class
 * @property Jquery $jquery                    Jquery Class
 * @property Utf8 $utf8                        Provides support for UTF-8 environments
 * @property Security $security                Security Class, xss, csrf, etc...
 * @property Driver_Library $driver            CodeIgniter Driver Library Class
 * @property Cache $cache                      CodeIgniter Caching Class
 */
class Controller {

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
		foreach (is_loaded() as $var => $class)
		{
			$this->$var =& load_class($class);
		}

		$this->load =& load_class('Loader', 'core');

		$this->load->initialize();

		log_message('debug', "Controller Class Initialized");
	}

	public static function &get_instance()
	{
		return self::$instance;
	}
}
// END Controller class

/* End of file Controller.php */
/* Location: ./system/core/Controller.php */
