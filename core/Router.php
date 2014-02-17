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
 * Router Class
 *
 * Parses URIs and determines routing
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @author    ExpressionEngine Dev Team
 * @category  Libraries
 * @link    http://codeigniter.com/user_guide/general/routing.html
 */
class Router
{

    /**
     * Config class
     *
     * @var Config
     * @access public
     */
    public $config;
    /**
     * List of routes
     *
     * @var array
     * @access public
     */
    public $routes = array();
    /**
     * List of error routes
     *
     * @var array
     * @access public
     */
    public $error_routes = array();
    /**
     * Current class name
     *
     * @var string
     * @access public
     */
    public $class = '';
    /**
     * Current file name
     *
     * @var string
     * @access public
     */
    public $file = '';
    /**
     * Current method name
     *
     * @var string
     * @access public
     */
    public $method = 'index';
    /**
     * Sub-directory that contains the requested controller class
     *
     * @var string
     * @access public
     */
    public $directory = '';
    /**
     * Default controller (and method if specific)
     *
     * @var string
     * @access public
     */
    public $default_controller;

    /**
     * @var URI
     */
    protected $uri;

    /**
     * Constructor
     *
     * Runs the route mapping function.
     */
    public function __construct()
    {
        $this->config =& load_class('Config', 'core');
        $this->uri    =& load_class('URI', 'core');
        log_message('debug', "Router Class Initialized");
    }

    // --------------------------------------------------------------------

    /**
     * Set the route mapping
     *
     * This function determines what should be served based on the URI request,
     * as well as any "routes" that have been set in the routing config file.
     *
     * @access  private
     * @return  void
     */
    public function setRouting()
    {
        // Are query strings enabled in the config file?  Normally CI doesn't utilize query strings
        // since URI segments are more search-engine friendly, but they can optionally be used.
        // If this feature is enabled, we will gather the directory/class/method a little differently
        $segments = array();
        if ($this->config->item('enable_query_strings') === true &&
            isset($_GET[$this->config->item(
                'controller_trigger'
            )])
        ) {
            if (isset($_GET[$this->config->item('directory_trigger')])) {
                $this->setDirectory(trim($this->uri->filterUri($_GET[$this->config->item('directory_trigger')])));
                $segments[] = $this->fetchDirectory();
            }

            if (isset($_GET[$this->config->item('controller_trigger')])) {
                $this->setClass(trim($this->uri->filterUri($_GET[$this->config->item('controller_trigger')])));
                $segments[] = $this->fetchClass();
            }

            if (isset($_GET[$this->config->item('function_trigger')])) {
                $this->setMethod(trim($this->uri->filterUri($_GET[$this->config->item('function_trigger')])));
                $segments[] = $this->fetchMethod();
            }
        }

        // Load the routes.php file.
        if (defined('ENVIRONMENT') && is_file(APPPATH . 'config/' . ENVIRONMENT . '/routes.php')) {
            include(APPPATH . 'config/' . ENVIRONMENT . '/routes.php');
        } elseif (is_file(APPPATH . 'config/routes.php')) {
            include(APPPATH . 'config/routes.php');
        }

        $this->routes = (!isset($route) || !is_array($route)) ? array() : $route;
        unset($route);

        // Set the default controller so we can display it in the event
        // the URI doesn't correlated to a valid controller.
        $this->default_controller = (
            !isset($this->routes['default_controller'])
            ||
            $this->routes['default_controller'] == '') ?
                false :
                strtolower($this->routes['default_controller']);

        // Were there any query string segments?  If so, we'll validate them and bail out since we're done.
        if (count($segments) > 0) {
            $this->validateRequest($segments);
            return;
        }

        // Fetch the complete URI string
        $this->uri->fetchUriString();

        // Is there a URI string? If not, the default controller specified in the "routes" file will be shown.
        if ($this->uri->uri_string == '') {
            $this->setDefaultController();
            return;
        }

        // Do we need to remove the URL suffix?
        $this->uri->removeUrlSuffix();

        // Compile the segments into an array
        $this->uri->explodeSegments();

        // Parse any custom routing that may exist
        $this->parseRoutes();

        // Re-index the segment array so that it starts with 1 rather than 0
        $this->uri->reindexSegments();
    }

    // --------------------------------------------------------------------

    /**
     * Set the default controller
     *
     * @access  private
     * @return  void
     */
    public function setDefaultController()
    {
        if ($this->default_controller === false) {
            show_error("Unable to determine what should be displayed.");
        }
        // Is the method being specified?
        if (strpos($this->default_controller, '/') !== false) {
            $x = explode('/', $this->default_controller);

            $this->setClass($x[0]);
            $this->setMethod($x[1]);
            $this->setRequest($x);
        } else {
            $this->setClass($this->default_controller);
            $this->setMethod('index');
            $this->setRequest(array($this->default_controller, 'index'));
        }

        // re-index the routed segments array so it starts with 1 rather than 0
        $this->uri->reindexSegments();

        log_message('debug', "No URI present. Default controller set.");
    }

    // --------------------------------------------------------------------

    /**
     * Set the Route
     *
     * This function takes an array of URI segments as
     * input, and sets the current class/method
     *
     * @access  private
     * @param  array $segments;
     * @return  void
     */
    public function setRequest($segments = array())
    {
        $segments = $this->validateRequest($segments);

        if (count($segments) == 0) {
            $this->setDefaultController();
            return;
        }

        $this->setClass($segments[0]);

        if (isset($segments[1])) {
            // A standard method request
            $this->setMethod($segments[1]);
        } else {
            // This lets the "routed" segment array identify that the default
            // index method is being used.
            $segments[1] = 'index';
        }

        // Update our "routed" segment array to contain the segments.
        // Note: If there is no custom routing, this array will be
        // identical to $this->uri->segments
        $this->uri->rsegments = $segments;
    }

    // --------------------------------------------------------------------

    /**
     * Validates the supplied segments.  Attempts to determine the path to
     * the controller.
     *
     * @access  private
     * @param  array
     * @return  array|void
     */
    public function validateRequest($segments)
    {
        if (count($segments) == 0) {
            return $segments;
        }

        // Does the requested controller exist in the root folder?
        if (file_exists(APPPATH . 'controllers/' . $segments[0] . '.php')) {
            return $segments;
        }

        // Is the controller in a sub-folder?
        if (is_dir(APPPATH . 'controllers/' . $segments[0])) {
            // Set the directory and remove it from the segment array
            $this->setDirectory($segments[0]);
            $segments = array_slice($segments, 1);

            if (count($segments) > 0) {
                // Does the requested controller exist in the sub-folder?
                if (!file_exists(APPPATH . 'controllers/' . $this->fetchDirectory() . $segments[0] . '.php')) {
                    if (!empty($this->routes['404_override'])) {
                        $x = explode('/', $this->routes['404_override']);

                        $this->setDirectory('');
                        $this->setClass($x[0]);
                        $this->setMethod(isset($x[1]) ? $x[1] : 'index');

                        return $x;
                    } else {
                        show_404($this->fetchDirectory() . $segments[0]);
                    }
                }
            } else {
                // Is the method being specified in the route?
                if (strpos($this->default_controller, '/') !== false) {
                    $x = explode('/', $this->default_controller);

                    $this->setClass($x[0]);
                    $this->setMethod($x[1]);
                } else {
                    $this->setClass($this->default_controller);
                    $this->setMethod('index');
                }

                // Does the default controller exist in the sub-folder?
                if (!file_exists(
                    APPPATH . 'controllers/' . $this->fetchDirectory() . $this->default_controller . '.php'
                )
                ) {
                    $this->directory = '';
                    return array();
                }

            }

            return $segments;
        }


        // If we've gotten this far it means that the URI does not correlate to a valid
        // controller class.  We will now see if there is an override
        if (!empty($this->routes['404_override'])) {
            $x = explode('/', $this->routes['404_override']);

            $this->setClass($x[0]);
            $this->setMethod(isset($x[1]) ? $x[1] : 'index');

            return $x;
        }


        // Nothing else to do at this point but show a 404
        show_404($segments[0]);
    }

    // --------------------------------------------------------------------

    /**
     *  Parse Routes
     *
     * This function matches any routes that may exist in
     * the config/routes.php file against the URI to
     * determine if the class/method need to be remapped.
     *
     * @access  private
     * @return  void
     */
    public function parseRoutes()
    {
        // Turn the segment array into a URI string
        $uri = implode('/', $this->uri->segments);

        // Is there a literal match?  If so we're done
        if (isset($this->routes[$uri])) {
            $this->setRequest(explode('/', $this->routes[$uri]));
            return;
        }

        // Loop through the route array looking for wild-cards
        foreach ($this->routes as $key => $val) {
            // Convert wild-cards to RegEx
            $key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));

            // Does the RegEx match?
            if (preg_match('#^' . $key . '$#', $uri)) {
                // Do we have a back-reference?
                if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                    $val = preg_replace('#^' . $key . '$#', $val, $uri);
                }

                $this->setRequest(explode('/', $val));
                return;
            }
        }

        // If we got this far it means we didn't encounter a
        // matching route so we'll set the site default route
        $this->setRequest($this->uri->segments);
    }

    // --------------------------------------------------------------------

    /**
     * Set the class name
     *
     * @access  public
     * @param  string $class
     * @return  void
     */
    public function setClass($class)
    {
        $file = str_replace(array('/', '.'), '', $class);
        $this->setFile($file);
        $this->class = config_item('app_namespace') . 'Controllers\\' . $file;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current class
     *
     * @access  public
     * @return  string
     */
    public function fetchClass()
    {
        return $this->class;
    }

    // --------------------------------------------------------------------

    /**
     * Set the file name
     *
     * @access  public
     * @param  string
     * @return  void
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current file
     *
     * @access  public
     * @return  string
     */
    public function fetchFile()
    {
        return $this->file;
    }

    // --------------------------------------------------------------------

    /**
     *  Set the method name
     *
     * @access  public
     * @param  string
     * @return  void
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    // --------------------------------------------------------------------

    /**
     *  Fetch the current method
     *
     * @access  public
     * @return  string
     */
    public function fetchMethod()
    {
        if ($this->method == $this->fetchClass()) {
            return 'index';
        }

        return $this->method;
    }

    // --------------------------------------------------------------------

    /**
     *  Set the directory name
     *
     * @access  public
     * @param  string
     * @return  void
     */
    public function setDirectory($dir)
    {
        $this->directory = str_replace(array('/', '.'), '', $dir) . '/';
    }

    // --------------------------------------------------------------------

    /**
     *  Fetch the sub-directory (if any) that contains the requested controller class
     *
     * @access  public
     * @return  string
     */
    public function fetchDirectory()
    {
        return $this->directory;
    }

    // --------------------------------------------------------------------

    /**
     *  Set the controller overrides
     *
     * @access  public
     * @param  array $routing
     * @return  null
     */
    public function setOverrides($routing)
    {
        if (!is_array($routing)) {
            return;
        }

        if (isset($routing['directory'])) {
            $this->setDirectory($routing['directory']);
        }

        if (isset($routing['controller']) && $routing['controller'] != '') {
            $this->setClass($routing['controller']);
        }

        if (isset($routing['function'])) {
            $routing['function'] = ($routing['function'] == '') ? 'index' : $routing['function'];
            $this->setMethod($routing['function']);
        }
    }
}
// END Router Class

/* End of file Router.php */
/* Location: ./system/core/Router.php */
