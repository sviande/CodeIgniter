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
 * URI Class
 *
 * Parses URIs and determines routing
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  URI
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/uri.html
 */
class URI
{
    /**
     * List of cached uri segments
     *
     * @var array
     * @access public
     */
    public $keyval = array();
    /**
     * Current uri string
     *
     * @var string
     * @access public
     */
    public $uri_string;
    /**
     * List of uri segments
     *
     * @var array
     * @access public
     */
    public $segments = array();
    /**
     * Re-indexed list of uri segments
     * Starts at 1 instead of 0
     *
     * @var array
     * @access public
     */
    public $rsegments = array();

    /**
     * Constructor
     *
     * Simply globalizes the $RTR object.  The front
     * loads the Router class early on so it's not available
     * normally as other classes are.
     *
     * @access  public
     */
    public function __construct()
    {
        $this->config =& load_class('Config', 'core');
        log_message('debug', "URI Class Initialized");
    }


    // --------------------------------------------------------------------

    /**
     * Get the URI String
     *
     * @access  private
     * @return  string
     */
    public function fetchUriString()
    {
        if (strtoupper($this->config->item('uri_protocol')) == 'AUTO') {
            // Is the request coming from the command line?
            if (php_sapi_name() == 'cli' or defined('STDIN')) {
                $this->setUriString($this->parseCliArgs());
                return;
            }

            // Let's try the REQUEST_URI first, this will work in most situations
            if ($uri = $this->detectUri()) {
                $this->setUriString($uri);
                return;
            }

            // Is there a PATH_INFO variable?
            // Note: some servers seem to have trouble with getenv() so we'll test it two ways
            $path = (isset($_SERVER['PATH_INFO'])) ? $_SERVER['PATH_INFO'] : @getenv('PATH_INFO');
            if (trim($path, '/') != '' && $path != "/" . SELF) {
                $this->setUriString($path);
                return;
            }

            // No PATH_INFO?... What about QUERY_STRING?
            $path = (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');
            if (trim($path, '/') != '') {
                $this->setUriString($path);
                return;
            }

            // As a last ditch effort lets try using the $_GET array
            if (is_array($_GET) && count($_GET) == 1 && trim(key($_GET), '/') != '') {
                $this->setUriString(key($_GET));
                return;
            }

            // We've exhausted all our options...
            $this->uri_string = '';
            return;
        }

        $uri = strtoupper($this->config->item('uri_protocol'));

        if ($uri == 'REQUEST_URI') {
            $this->setUriString($this->detectUri());
            return;
        } elseif ($uri == 'CLI') {
            $this->setUriString($this->parseCliArgs());
            return;
        }

        $path = (isset($_SERVER[$uri])) ? $_SERVER[$uri] : @getenv($uri);
        $this->setUriString($path);
    }

    // --------------------------------------------------------------------

    /**
     * Set the URI String
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function setUriString($str)
    {
        // Filter out control characters
        $str = remove_invisible_characters($str, false);

        // If the URI contains only a slash we'll kill it
        $this->uri_string = ($str == '/') ? '' : $str;
    }

    // --------------------------------------------------------------------

    /**
     * Detects the URI
     *
     * This function will detect the URI automatically and fix the query string
     * if necessary.
     *
     * @access  private
     * @return  string
     */
    private function detectUri()
    {
        if (!isset($_SERVER['REQUEST_URI']) || !isset($_SERVER['SCRIPT_NAME'])) {
            return '';
        }

        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            $uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
        } elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
            $uri = substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
        }

        // This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
        // URI is found, and also fixes the QUERY_STRING server var and $_GET array.
        if (strncmp($uri, '?/', 2) === 0) {
            $uri = substr($uri, 2);
        }
        $parts = preg_split('#\?#i', $uri, 2);
        $uri = $parts[0];
        if (isset($parts[1])) {
            $_SERVER['QUERY_STRING'] = $parts[1];
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        } else {
            $_SERVER['QUERY_STRING'] = '';
            $_GET = array();
        }

        if ($uri == '/' || empty($uri)) {
            return '/';
        }

        $uri = parse_url($uri, PHP_URL_PATH);

        // Do some final cleaning of the URI and return it
        return str_replace(array('//', '../'), '/', trim($uri, '/'));
    }

    // --------------------------------------------------------------------

    /**
     * Parse cli arguments
     *
     * Take each command line argument and assume it is a URI segment.
     *
     * @access  private
     * @return  string
     */
    private function parseCliArgs()
    {
        $args = array_slice($_SERVER['argv'], 1);

        return $args ? '/' . implode('/', $args) : '';
    }

    // --------------------------------------------------------------------

    /**
     * Filter segments for malicious characters
     *
     * @access  private
     * @param  string
     * @return  string
     */
    public function filterUri($str)
    {
        if ($str != '' && $this->config->item('permitted_uri_chars') != ''
            && $this->config->item('enable_query_strings') == false
        ) {
            // preg_quote() in PHP 5.3 escapes -, so the str_replace() and addition of - to preg_quote() is to maintain backwards
            // compatibility as many are unaware of how characters in the permitted_uri_chars will be parsed as a regex pattern
            if (!preg_match(
                "|^[" . str_replace(
                    array('\\-', '\-'),
                    '-',
                    preg_quote($this->config->item('permitted_uri_chars'), '-')
                ) . "]+$|i",
                $str
            )
            ) {
                show_error('The URI you submitted has disallowed characters.', 400);
            }
        }

        // Convert programatic characters to entities
        $bad = array('$', '(', ')', '%28', '%29');
        $good = array('&#36;', '&#40;', '&#41;', '&#40;', '&#41;');

        return str_replace($bad, $good, $str);
    }

    // --------------------------------------------------------------------

    /**
     * Remove the suffix from the URL if needed
     *
     * @access  private
     * @return  void
     */
    public function removeUrlSuffix()
    {
        if ($this->config->item('url_suffix') != "") {
            $this->uri_string = preg_replace(
                "|" . preg_quote($this->config->item('url_suffix')) . "$|",
                "",
                $this->uri_string
            );
        }
    }

    // --------------------------------------------------------------------

    /**
     * Explode the URI Segments. The individual segments will
     * be stored in the $this->segments array.
     *
     * @access  private
     * @return  void
     */
    public function explodeSegments()
    {
        foreach (explode("/", preg_replace("|/*(.+?)/*$|", "\\1", $this->uri_string)) as $val) {
            // Filter segments for security
            $val = trim($this->filterUri($val));

            if ($val != '') {
                $this->segments[] = $val;
            }
        }
    }

    // --------------------------------------------------------------------
    /**
     * Re-index Segments
     *
     * This function re-indexes the $this->segment array so that it
     * starts at 1 rather than 0.  Doing so makes it simpler to
     * use functions like $this->uri->segment(n) since there is
     * a 1:1 relationship between the segment array and the actual segments.
     *
     * @access  private
     * @return  void
     */
    public function reindexSegments()
    {
        array_unshift($this->segments, null);
        array_unshift($this->rsegments, null);
        unset($this->segments[0]);
        unset($this->rsegments[0]);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a URI Segment
     *
     * This function returns the URI segment based on the number provided.
     *
     * @access  public
     * @param  integer
     * @param  bool
     * @return  string
     */
    public function segment($n, $no_result = false)
    {
        return (!isset($this->segments[$n])) ? $no_result : $this->segments[$n];
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a URI "routed" Segment
     *
     * This function returns the re-routed URI segment (assuming routing rules are used)
     * based on the number provided.  If there is no routing this function returns the
     * same result as $this->segment()
     *
     * @access  public
     * @param  integer $n
     * @param  bool $no_result
     * @return  string
     */
    public function rsegment($n, $no_result = false)
    {
        return (!isset($this->rsegments[$n])) ? $no_result : $this->rsegments[$n];
    }

    // --------------------------------------------------------------------

    /**
     * Identical to above only it uses the re-routed segment array
     *
     * @access  public
     * @param  integer $n the starting segment number
     * @param  array $default an array of default values
     * @return  array
     *
     */
    public function ruriToAssoc($n = 3, $default = array())
    {
        return $this->uriToAssoc($n, $default, 'rsegment');
    }

    // --------------------------------------------------------------------

    /**
     * Generate a key value pair from the URI string or Re-routed URI string
     *
     * @access  private
     * @param  integer $n the starting segment number
     * @param  array $default an array of default values
     * @param  string $which which array we should use
     * @return  array
     */
    public function uriToAssoc($n = 3, $default = array(), $which = 'segment')
    {
        if ($which == 'segment') {
            $total_segments = 'totalSegments';
            $segment_array = 'segmentArray';
        } else {
            $total_segments = 'totalRsegments';
            $segment_array = 'rsegmentArray';
        }

        if (!is_numeric($n)) {
            return $default;
        }

        if (isset($this->keyval[$n])) {
            return $this->keyval[$n];
        }

        if ($this->$total_segments() < $n) {
            if (count($default) == 0) {
                return array();
            }

            $retval = array();
            foreach ($default as $val) {
                $retval[$val] = false;
            }
            return $retval;
        }

        $segments = array_slice($this->$segment_array(), ($n - 1));

        $i = 0;
        $lastval = '';
        $retval = array();
        foreach ($segments as $seg) {
            if ($i % 2) {
                $retval[$lastval] = $seg;
            } else {
                $retval[$seg] = false;
                $lastval = $seg;
            }

            $i++;
        }

        if (count($default) > 0) {
            foreach ($default as $val) {
                if (!array_key_exists($val, $retval)) {
                    $retval[$val] = false;
                }
            }
        }

        // Cache the array for reuse
        $this->keyval[$n] = $retval;
        return $retval;
    }

    // --------------------------------------------------------------------

    /**
     * Generate a URI string from an associative array
     *
     *
     * @access  public
     * @param  array $array an associative array of key/values
     * @return  array
     */
    public function assocToUri($array)
    {
        $temp = array();
        foreach ((array)$array as $key => $val) {
            $temp[] = $key;
            $temp[] = $val;
        }

        return implode('/', $temp);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a URI Segment and add a trailing slash
     *
     * @access  public
     * @param  integer
     * @param  string
     * @return  string
     */
    public function slashRsegment($n, $where = 'trailing')
    {
        return $this->slashSegment($n, $where, 'rsegment');
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a URI Segment and add a trailing slash - helper function
     *
     * @access  private
     * @param  integer
     * @param  string
     * @param  string
     * @return  string
     */
    public function slashSegment($n, $where = 'trailing', $which = 'segment')
    {
        $leading = '/';
        $trailing = '/';

        if ($where == 'trailing') {
            $leading = '';
        } elseif ($where == 'leading') {
            $trailing = '';
        }

        return $leading . $this->$which($n) . $trailing;
    }

    // --------------------------------------------------------------------

    /**
     * Segment Array
     *
     * @access  public
     * @return  array
     */
    public function segmentArray()
    {
        return $this->segments;
    }

    // --------------------------------------------------------------------

    /**
     * Routed Segment Array
     *
     * @access  public
     * @return  array
     */
    public function rsegmentArray()
    {
        return $this->rsegments;
    }

    // --------------------------------------------------------------------

    /**
     * Total number of segments
     *
     * @access  public
     * @return  integer
     */
    public function totalSegments()
    {
        return count($this->segments);
    }

    // --------------------------------------------------------------------

    /**
     * Total number of routed segments
     *
     * @access  public
     * @return  integer
     */
    public function totalRsegments()
    {
        return count($this->rsegments);
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the entire URI string
     *
     * @access  public
     * @return  string
     */
    public function uriString()
    {
        return $this->uri_string;
    }


    // --------------------------------------------------------------------

    /**
     * Fetch the entire Re-routed URI string
     *
     * @access  public
     * @return  string
     */
    public function ruriString()
    {
        return '/' . implode('/', $this->rsegmentArray());
    }
}
// END URI Class

/* End of file URI.php */
/* Location: ./system/core/URI.php */
