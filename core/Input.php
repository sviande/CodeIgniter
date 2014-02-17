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
 * Input Class
 *
 * Pre-processes global input data for security
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Input
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/input.html
 */
class Input
{

    /**
     * IP address of the current user
     *
     * @var string
     */
    public $ip_address = false;
    /**
     * user agent (web browser) being used by the current user
     *
     * @var string
     */
    public $user_agent = false;
    /**
     * If FALSE, then $_GET will be set to an empty array
     *
     * @var bool
     */
    protected $allow_get_array = true;
    /**
     * If TRUE, then newlines are standardized
     *
     * @var bool
     */
    protected $standardize_newlines = true;
    /**
     * Determines whether the XSS filter is always active when GET, POST or COOKIE data is encountered
     * Set automatically based on config setting
     *
     * @var bool
     */
    protected $enable_xss = false;
    /**
     * Enables a CSRF cookie token to be set.
     * Set automatically based on config setting
     *
     * @var bool
     */
    protected $enable_csrf = false;
    /**
     * List of all HTTP request headers
     *
     * @var array
     */
    protected $headers = array();

    /**
     * Constructor
     *
     * Sets whether to globally enable the XSS processing
     * and whether to allow the $_GET array
     *
     */
    public function __construct()
    {
        log_message('debug', "Input Class Initialized");

        $this->allow_get_array = (config_item('allow_get_array') === true);
        $this->enable_xss = (config_item('global_xss_filtering') === true);
        $this->enable_csrf = (config_item('csrf_protection') === true);

        global $SEC;
        $this->security =& $SEC;

        // Do we need the UTF-8 class?
        if (UTF8_ENABLED === true) {
            global $UNI;
            $this->uni =& $UNI;
        }

        // Sanitize global arrays
        $this->sanitizeGlobals();
    }

    // --------------------------------------------------------------------

    /**
     * Fetch from array
     *
     * This is a helper function to retrieve values from global arrays
     *
     * @access  private
     * @param $array array
     * @param $index string
     * @param $xss_clean bool
     * @return  string
     */
    protected function fetchFromArray(&$array, $index = '', $xss_clean = false)
    {
        if (!isset($array[$index])) {
            return false;
        }

        if ($xss_clean === true) {
            return $this->security->xssClean($array[$index]);
        }

        return $array[$index];
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the GET array
     *
     * @access  public
     * @param  string $index
     * @param  bool   $xss_clean
     * @return  string
     */
    public function get($index = null, $xss_clean = false)
    {
        // Check if a field has been provided
        if ($index === null && !empty($_GET)) {
            $get = array();

            // loop through the full _GET array
            foreach (array_keys($_GET) as $key) {
                $get[$key] = $this->fetchFromArray($_GET, $key, $xss_clean);
            }
            return $get;
        }

        return $this->fetchFromArray($_GET, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the POST array
     *
     * @access  public
     * @param  string $index
     * @param  bool   $xss_clean
     *
     * @return  string
     */
    public function post($index = null, $xss_clean = false)
    {
        // Check if a field has been provided
        if ($index === null && !empty($_POST)) {
            $post = array();

            // Loop through the full _POST array and return it
            foreach (array_keys($_POST) as $key) {
                $post[$key] = $this->fetchFromArray($_POST, $key, $xss_clean);
            }
            return $post;
        }

        return $this->fetchFromArray($_POST, $index, $xss_clean);
    }


    // --------------------------------------------------------------------

    /**
     * Fetch an item from either the GET array or the POST
     *
     * @access  public
     * @param  string $index The index key
     * @param  bool   $xss_clean XSS cleaning
     * @return  string
     */
    public function getPost($index = '', $xss_clean = false)
    {
        if (!isset($_POST[$index])) {
            return $this->get($index, $xss_clean);
        } else {
            return $this->post($index, $xss_clean);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the COOKIE array
     *
     * @access  public
     * @param  string
     * @param  bool
     * @return  string
     */
    public function cookie($index = '', $xss_clean = false)
    {
        return $this->fetchFromArray($_COOKIE, $index, $xss_clean);
    }

    // ------------------------------------------------------------------------

    /**
     * Set cookie
     *
     * Accepts six parameter, or you can submit an associative
     * array in the first parameter containing all the values.
     *
     * @access  public
     * @param  mixed  $name
     * @param  string $value the value of the cookie
     * @param  string $expire the number of seconds until expiration
     * @param  string $domain the cookie domain.  Usually:  .yourdomain.com
     * @param  string $path the cookie path
     * @param  string $prefix the cookie prefix
     * @param  bool   $secure true makes the cookie secure
     * @return  void
     */
    public function setCookie(
        $name = '',
        $value = '',
        $expire = '',
        $domain = '',
        $path = '/',
        $prefix = '',
        $secure = false
    ) {
        if (is_array($name)) {
            // always leave 'name' in last place, as the loop will break otherwise, due to $$item
            foreach (array('value', 'expire', 'domain', 'path', 'prefix', 'secure', 'name') as $item) {
                if (isset($name[$item])) {
                    $$item = $name[$item];
                }
            }
        }

        if ($prefix == '' && config_item('cookie_prefix') != '') {
            $prefix = config_item('cookie_prefix');
        }
        if ($domain == '' && config_item('cookie_domain') != '') {
            $domain = config_item('cookie_domain');
        }
        if ($path == '/' && config_item('cookie_path') != '/') {
            $path = config_item('cookie_path');
        }
        if ($secure == false && config_item('cookie_secure') != false) {
            $secure = config_item('cookie_secure');
        }

        if (!is_numeric($expire)) {
            $expire = time() - 86500;
        } else {
            $expire = ($expire > 0) ? time() + $expire : 0;
        }

        setcookie($prefix . $name, $value, $expire, $path, $domain, $secure);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from the SERVER array
     *
     * @access  public
     * @param  string $index
     * @param  bool   $xss_clean
     * @return  string
     */
    public function server($index = '', $xss_clean = false)
    {
        return $this->fetchFromArray($_SERVER, $index, $xss_clean);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the IP Address
     *
     * @return  string
     */
    public function ipAddress()
    {
        if ($this->ip_address !== false) {
            return $this->ip_address;
        }

        $proxy_ips = config_item('proxy_ips');
        if (!empty($proxy_ips)) {
            $proxy_ips = explode(',', str_replace(' ', '', $proxy_ips));
            foreach (array(
                         'HTTP_X_FORWARDED_FOR',
                         'HTTP_CLIENT_IP',
                         'HTTP_X_CLIENT_IP',
                         'HTTP_X_CLUSTER_CLIENT_IP'
                     ) as $header) {
                if (($spoof = $this->server($header)) !== false) {
                    // Some proxies typically list the whole chain of IP
                    // addresses through which the client has reached us.
                    // e.g. client_ip, proxy_ip1, proxy_ip2, etc.
                    if (strpos($spoof, ',') !== false) {
                        $spoof = explode(',', $spoof, 2);
                        $spoof = $spoof[0];
                    }

                    if (!$this->validIp($spoof)) {
                        $spoof = false;
                    } else {
                        break;
                    }
                }
            }

            $this->ip_address = ($spoof !== false && in_array($_SERVER['REMOTE_ADDR'], $proxy_ips, true))
                ? $spoof : $_SERVER['REMOTE_ADDR'];
        } else {
            $this->ip_address = $_SERVER['REMOTE_ADDR'];
        }

        if (!$this->validIp($this->ip_address)) {
            $this->ip_address = '0.0.0.0';
        }

        return $this->ip_address;
    }

    // --------------------------------------------------------------------

    /**
     * Validate IP Address
     *
     * @access  public
     * @param  string $ip
     * @param  string $which ipv4 or ipv6
     * @return  bool
     */
    public function validIp($ip, $which = '')
    {
        $which = strtolower($which);

        // First check if filter_var is available
        if (is_callable('filter_var')) {
            switch ($which) {
                case 'ipv4':
                    $flag = FILTER_FLAG_IPV4;
                    break;
                case 'ipv6':
                    $flag = FILTER_FLAG_IPV6;
                    break;
                default:
                    $flag = '';
                    break;
            }

            return (bool)filter_var($ip, FILTER_VALIDATE_IP, $flag);
        }

        if ($which !== 'ipv6' && $which !== 'ipv4') {
            if (strpos($ip, ':') !== false) {
                $which = 'ipv6';
            } elseif (strpos($ip, '.') !== false) {
                $which = 'ipv4';
            } else {
                return false;
            }
        }

        $func = '_valid_' . $which;
        return $this->$func($ip);
    }

    // --------------------------------------------------------------------

    /**
     * @todo 5.3 >
     * Validate IPv4 Address
     *
     * Updated version suggested by Geert De Deckere
     *
     * @access  protected
     * @param  string
     * @return  bool
     */
    protected function _valid_ipv4($ip)
    {
        $ip_segments = explode('.', $ip);

        // Always 4 segments needed
        if (count($ip_segments) !== 4) {
            return false;
        }
        // IP can not start with 0
        if ($ip_segments[0][0] == '0') {
            return false;
        }

        // Check each segment
        foreach ($ip_segments as $segment) {
            // IP segments must be digits and can not be
            // longer than 3 digits or greater then 255
            if ($segment == '' || preg_match("/[^0-9]/", $segment) || $segment > 255 || strlen($segment) > 3) {
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     *
     * @todo remove filter_var(FLAG_IPV6);
     * Validate IPv6 Address
     *
     * @access  protected
     * @param  string
     * @return  bool
     */
    protected function _valid_ipv6($str)
    {
        // 8 groups, separated by :
        // 0-ffff per group
        // one set of consecutive 0 groups can be collapsed to ::

        $groups = 8;
        $collapsed = false;

        $chunks = array_filter(
            preg_split('/(:{1,2})/', $str, null, PREG_SPLIT_DELIM_CAPTURE)
        );

        // Rule out easy nonsense
        if (current($chunks) == ':' || end($chunks) == ':') {
            return false;
        }

        // PHP supports IPv4-mapped IPv6 addresses, so we'll expect those as well
        if (strpos(end($chunks), '.') !== false) {
            $ipv4 = array_pop($chunks);

            if (!$this->_valid_ipv4($ipv4)) {
                return false;
            }

            $groups--;
        }

        while ($seg = array_pop($chunks)) {
            if ($seg[0] == ':') {
                if (--$groups == 0) {
                    return false; // too many groups
                }

                if (strlen($seg) > 2) {
                    return false; // long separator
                }

                if ($seg == '::') {
                    if ($collapsed) {
                        return false; // multiple collapsed
                    }

                    $collapsed = true;
                }
            } elseif (preg_match("/[^0-9a-f]/i", $seg) || strlen($seg) > 4) {
                return false; // invalid segment
            }
        }

        return $collapsed || $groups == 1;
    }

    // --------------------------------------------------------------------

    /**
     * User Agent
     *
     * @access  public
     * @return  string
     */
    public function userAgent()
    {
        if ($this->user_agent !== false) {
            return $this->user_agent;
        }

        $this->user_agent = (!isset($_SERVER['HTTP_USER_AGENT'])) ? false : $_SERVER['HTTP_USER_AGENT'];

        return $this->user_agent;
    }

    // --------------------------------------------------------------------

    /**
     * Sanitize Globals
     *
     * This function does the following:
     *
     * Unsets $_GET data (if query strings are not enabled)
     *
     * Unsets all globals if register_globals is enabled
     *
     * Standardizes newline characters to \n
     *
     * @access  private
     * @return  void
     */
    public function sanitizeGlobals()
    {
        // It would be "wrong" to unset any of these GLOBALS.
        $protected = array(
            '_SERVER',
            '_GET',
            '_POST',
            '_FILES',
            '_REQUEST',
            '_SESSION',
            '_ENV',
            'GLOBALS',
            'HTTP_RAW_POST_DATA',
            'system_folder',
            'application_folder',
            'BM',
            'EXT',
            'CFG',
            'URI',
            'RTR',
            'OUT',
            'IN'
        );

        // Unset globals for securiy.
        // This is effectively the same as register_globals = off
        foreach (array($_GET, $_POST, $_COOKIE) as $global) {
            if (!is_array($global)) {
                if (!in_array($global, $protected)) {
                    global $$global;
                    $$global = null;
                }
            } else {
                foreach ($global as $key => $val) {
                    if (!in_array($key, $protected)) {
                        global $$key;
                        $$key = null;
                    }
                }
            }
        }

        // Is $_GET data allowed? If not we'll set the $_GET to an empty array
        if ($this->allow_get_array == false) {
            $_GET = array();
        } else {
            if (is_array($_GET) && count($_GET) > 0) {
                foreach ($_GET as $key => $val) {
                    $_GET[$this->cleanInputKeys($key)] = $this->cleanInputData($val);
                }
            }
        }

        // Clean $_POST Data
        if (is_array($_POST) && count($_POST) > 0) {
            foreach ($_POST as $key => $val) {
                $_POST[$this->cleanInputKeys($key)] = $this->cleanInputData($val);
            }
        }

        // Clean $_COOKIE Data
        if (is_array($_COOKIE) && count($_COOKIE) > 0) {
            // Also get rid of specially treated cookies that might be set by a server
            // or silly application, that are of no use to a CI application anyway
            // but that when present will trip our 'Disallowed Key Characters' alarm
            // http://www.ietf.org/rfc/rfc2109.txt
            // note that the key names below are single quoted strings, and are not PHP variables
            unset($_COOKIE['$Version']);
            unset($_COOKIE['$Path']);
            unset($_COOKIE['$Domain']);

            foreach ($_COOKIE as $key => $val) {
                $_COOKIE[$this->cleanInputKeys($key)] = $this->cleanInputData($val);
            }
        }

        // Sanitize PHP_SELF
        $_SERVER['PHP_SELF'] = strip_tags($_SERVER['PHP_SELF']);


        // CSRF Protection check on HTTP requests
        if ($this->enable_csrf == true && !$this->isCliRequest()) {
            $this->security->csrfVerify();
        }

        log_message('debug', "Global POST and COOKIE data sanitized");
    }

    // --------------------------------------------------------------------

    /**
     * Clean Input Data
     *
     * This is a helper function. It escapes data and
     * standardizes newline characters to \n
     *
     * @access  private
     * @param  string
     * @return  string
     */
    public function cleanInputData($str)
    {
        if (is_array($str)) {
            $new_array = array();
            foreach ($str as $key => $val) {
                $new_array[$this->cleanInputKeys($key)] = $this->cleanInputData($val);
            }
            return $new_array;
        }

        /* We strip slashes if magic quotes is on to keep things consistent

           NOTE: In PHP 5.4 get_magic_quotes_gpc() will always return 0 and
           it will probably not exist in future versions at all.
        */
        if (!is_php('5.4') && get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }

        // Clean UTF-8 if supported
        if (UTF8_ENABLED === true) {
            $str = $this->uni->cleanString($str);
        }

        // Remove control characters
        $str = remove_invisible_characters($str);

        // Should we filter the input data?
        if ($this->enable_xss === true) {
            $str = $this->security->xssClean($str);
        }

        // Standardize newlines if needed
        if ($this->standardize_newlines == true) {
            if (strpos($str, "\r") !== false) {
                $str = str_replace(array("\r\n", "\r", "\r\n\n"), PHP_EOL, $str);
            }
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Clean Keys
     *
     * This is a helper function. To prevent malicious users
     * from trying to exploit keys we make sure that keys are
     * only named with alpha-numeric text and a few other items.
     *
     * @access  private
     * @param  string
     * @return  string
     */
    public function cleanInputKeys($str)
    {
        if (!preg_match("/^[a-z0-9:_\/-]+$/i", $str)) {
            exit('Disallowed Key Characters.');
        }

        // Clean UTF-8 if supported
        if (UTF8_ENABLED === true) {
            $str = $this->uni->cleanString($str);
        }

        return $str;
    }

    // --------------------------------------------------------------------

    /**
     * Request Headers
     *
     * In Apache, you can simply call apache_request_headers(), however for
     * people running other webservers the function is undefined.
     *
     * @param  bool $xss_clean XSS cleaning
     *
     * @return array
     */
    public function requestHeaders($xss_clean = false)
    {
        // Look at Apache go!
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            $headers['Content-Type'] = (isset($_SERVER['CONTENT_TYPE'])) ? $_SERVER['CONTENT_TYPE'] : @getenv(
                'CONTENT_TYPE'
            );

            foreach ($_SERVER as $key => $val) {
                if (strncmp($key, 'HTTP_', 5) === 0) {
                    $headers[substr($key, 5)] = $this->fetchFromArray($_SERVER, $key, $xss_clean);
                }
            }
        }

        // take SOME_HEADER and turn it into Some-Header
        foreach ($headers as $key => $val) {
            $key = str_replace('_', ' ', strtolower($key));
            $key = str_replace(' ', '-', ucwords($key));

            $this->headers[$key] = $val;
        }

        return $this->headers;
    }

    // --------------------------------------------------------------------

    /**
     * Get Request Header
     *
     * Returns the value of a single member of the headers class member
     *
     * @param  string  $index array key for $this->headers
     * @param  boolean $xss_clean XSS Clean or not
     * @return  mixed    FALSE on failure, string on success
     */
    public function getRequestHeader($index, $xss_clean = false)
    {
        if (empty($this->headers)) {
            $this->requestHeaders();
        }

        if (!isset($this->headers[$index])) {
            return false;
        }

        if ($xss_clean === true) {
            return $this->security->xssClean($this->headers[$index]);
        }

        return $this->headers[$index];
    }

    // --------------------------------------------------------------------

    /**
     * Is ajax Request?
     *
     * Test to see if a request contains the HTTP_X_REQUESTED_WITH header
     *
     * @return  boolean
     */
    public function isAjaxRequest()
    {
        return ($this->server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest');
    }

    // --------------------------------------------------------------------

    /**
     * Is cli Request?
     *
     * Test to see if a request was made from the command line
     *
     * @return  bool
     */
    public function isCliRequest()
    {
        return (php_sapi_name() === 'cli' || defined('STDIN'));
    }
}

/* End of file Input.php */
/* Location: ./system/core/Input.php */
