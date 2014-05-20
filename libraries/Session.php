<?php
namespace CI\Libraries;

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
 * Session Class
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Sessions
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/sessions.html
 */
class Session
{
    public $sess_encrypt_cookie = false;
    public $sess_expiration = 7200;
    public $sess_expire_on_close = false;
    public $sess_match_ip = false;
    public $sess_match_useragent = true;
    public $sess_cookie_name = 'ci_session';
    public $cookie_prefix = '';
    public $cookie_path = '';
    public $cookie_domain = '';
    public $cookie_secure = false;
    public $sess_time_to_update = 300;
    public $flashdata_key = 'flash';
    public $time_reference = 'time';
    protected  $userdata = array();
    protected $infos = array();
    protected $CI;
    public $now;

    /**
     * Session Constructor
     *
     * The constructor runs the session routines automatically
     * whenever the class is instantiated.
     */
    public function __construct($params = array())
    {
        \CI\Core\log_message('debug', "Session Class Initialized");

        // Set the super object to a local variable for use throughout the class
        $this->CI =& \CI\Core\get_instance();

        // Set all the session preferences, which can either be set
        // manually via the $params array above or via the config file
        foreach (array(
                     'sess_encrypt_cookie',
                     'sess_expiration',
                     'sess_expire_on_close',
                     'sess_match_ip',
                     'sess_match_useragent',
                     'sess_cookie_name',
                     'cookie_path',
                     'cookie_domain',
                     'cookie_secure',
                     'sess_time_to_update',
                     'time_reference',
                     'cookie_prefix',
                     'encryption_key'
                 ) as $key) {
            $this->$key = (isset($params[$key])) ? $params[$key] : $this->CI->config->item($key);
        }

        if ($this->encryption_key == '') {
            \CI\Core\show_error(
                'In order to use the Session class you are required to set an encryption key in your config file.'
            );
        }

        // Load the string helper so we can use the strip_slashes() function
        $this->CI->load->helper('string');

        // Do we need encryption? If so, load the encryption class
        if ($this->sess_encrypt_cookie == true) {
            $this->CI->load->library('encrypt');
        }

        // Set the "now" time.  Can either be GMT or server time, based on the
        // config prefs.  We use this to set the "last activity" time
        $this->now = $this->getTime();

        // Set the session length. If the session expiration is
        // set to zero we'll set the expiration two years from now.
        if ($this->sess_expiration == 0) {
            $this->sess_expiration = (60 * 60 * 24 * 365 * 2);
        }

        // Set the cookie name
        $this->sess_cookie_name = $this->cookie_prefix . $this->sess_cookie_name;

        session_name(md5($this->sess_cookie_name));
        session_start();

        // Run the Session routine. If a session doesn't exist we'll
        // create a new one.  If it does, we'll update it.
        if (!$this->sessRead()) {
            $this->sessCreate();
        } else {
            $this->sessUpdate();
        }

        // Delete 'old' flashdata (from last request)
        $this->flashdataSweep();

        // Mark all new flashdata as old (data will be deleted before next request)
        $this->flashdataMark();

        \CI\Core\log_message('debug', "Session routines successfully run");
    }

    // --------------------------------------------------------------------

    /**
     * Fetch the current session data if it exists
     *
     * @access  public
     * @return  bool
     */
    public function sessRead()
    {
        if (session_status() != PHP_SESSION_ACTIVE) {
            \CI\Core\log_message('debug', 'A session cookie was not found.');
            return false;
        }
        // Is the session data we unserialized an array with the correct format?
        $this->infos = &$_SESSION['infos'];
        $this->userdata = &$_SESSION['user_data'];

        if (!is_array($this->infos)
            || !isset($this->infos['session_id'])
            || !isset($this->infos['ip_address'])
            || !isset($this->infos['userAgent'])
            || !isset($this->infos['last_activity'])
        ) {
            return false;
        }

        // Is the session current?
        if (($this->infos['last_activity'] + $this->sess_expiration) < $this->now) {
            $this->sessDestroy();
            return false;
        }

        // Does the IP Match?
        if ($this->sess_match_ip == true && $this->infos['ip_address'] != $this->CI->input->ipAddress()) {
            $this->sessDestroy();
            return false;
        }

        // Does the User Agent Match?
        if ($this->sess_match_useragent == true
            && trim($this->infos['userAgent']) != trim(substr($this->CI->input->userAgent(), 0, 120))
        ) {
            $this->sessDestroy();
            return false;
        }

        // Session is valid!
        return true;
    }
    // --------------------------------------------------------------------

    /**
     * Create a new session
     *
     * @access  public
     * @return  void
     */
    public function sessCreate()
    {
        $sessid = '';
        while (strlen($sessid) < 32) {
            $sessid .= mt_rand(0, mt_getrandmax());
        }

        // To make the session ID even more secure we'll combine it with the user's IP
        $sessid .= $this->CI->input->ipAddress();

        $this->infos = array(
            'session_id'    => md5(uniqid($sessid, true)),
            'ip_address'    => $this->CI->input->ipAddress(),
            'userAgent'     => substr($this->CI->input->userAgent(), 0, 120),
            'last_activity' => $this->now
        );

        $this->userdata = array();
    }

    // --------------------------------------------------------------------

    /**
     * Update an existing session
     *
     * @access  public
     * @return  void
     */
    public function sessUpdate()
    {
        // We only update the session every five minutes by default
        if (($this->infos['last_activity'] + $this->sess_time_to_update) >= $this->now) {
            return;
        }

        $new_sessid = '';
        while (strlen($new_sessid) < 32) {
            $new_sessid .= mt_rand(0, mt_getrandmax());
        }

        // To make the session ID even more secure we'll combine it with the user's IP
        $new_sessid .= $this->CI->input->ipAddress();

        // Turn it into a hash
        $new_sessid = md5(uniqid($new_sessid, true));

        // Update the session data in the session data array
        $this->infos['session_id']    = $new_sessid;
        $this->infos['last_activity'] = $this->now;
    }

    public function close()
    {
        session_write_close();
    }

    // --------------------------------------------------------------------

    /**
     * Destroy the current session
     *
     * @access  public
     * @return  void
     */
    public function sessDestroy()
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        // Kill session data
        $this->userdata = array();
        $this->infos    = array();
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a specific item from the session array
     *
     * @access  public
     * @param  string $item
     * @return  mixed
     */
    public function userdata($item)
    {
        return (!isset($this->userdata[$item])) ? false : $this->userdata[$item];
    }

    // --------------------------------------------------------------------

    /**
     * Fetch all session data
     *
     * @access  public
     * @return  array
     */
    public function allUserdata()
    {
        return $this->userdata;
    }

    // --------------------------------------------------------------------

    /**
     * Add or change data in the "userdata" array
     *
     * @access  public
     * @param  mixed  $newdata
     * @param  string $newval
     * @return  void
     */
    public function setUserdata($newdata = array(), $newval = '')
    {
        if (is_string($newdata)) {
            $newdata = array($newdata => $newval);
        }

        if (count($newdata) > 0) {
            foreach ($newdata as $key => $val) {
                $this->userdata[$key] = $val;
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Delete a session variable from the "userdata" array
     *
     * @access  public
     * @param array $newdata
     * @return  void
     */
    public function unsetUserdata($newdata = array())
    {
        if (is_string($newdata)) {
            $newdata = array($newdata => '');
        }

        if (count($newdata) > 0) {
            foreach ($newdata as $key => $val) {
                unset($this->userdata[$key]);
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Add or change flashdata, only available
     * until the next request
     *
     * @access  public
     * @param  mixed  $newdata
     * @param  string $newval
     * @return  void
     */
    public function setFlashdata($newdata = array(), $newval = '')
    {
        if (is_string($newdata)) {
            $newdata = array($newdata => $newval);
        }

        if (count($newdata) > 0) {
            foreach ($newdata as $key => $val) {
                $flashdata_key = $this->flashdata_key . ':new:' . $key;
                $this->setUserdata($flashdata_key, $val);
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Keeps existing flashdata available to next request.
     *
     * @access  public
     * @param  string
     * @return  void
     */
    public function keepFlashdata($key)
    {
        // 'old' flashdata gets removed.  Here we mark all
        // flashdata as 'new' to preserve it from flashdataSweep()
        // Note the function will return FALSE if the $key
        // provided cannot be found
        $old_flashdata_key = $this->flashdata_key . ':old:' . $key;
        $value             = $this->userdata($old_flashdata_key);

        $new_flashdata_key = $this->flashdata_key . ':new:' . $key;
        $this->setUserdata($new_flashdata_key, $value);
    }

    // ------------------------------------------------------------------------

    /**
     * Fetch a specific flashdata item from the session array
     *
     * @access  public
     * @param  string
     * @return  string
     */
    public function flashdata($key)
    {
        $flashdata_key = $this->flashdata_key . ':old:' . $key;
        return $this->userdata($flashdata_key);
    }

    // ------------------------------------------------------------------------

    /**
     * Identifies flashdata as 'old' for removal
     * when flashdataSweep() runs.
     *
     * @access  private
     * @return  void
     */
    protected function flashdataMark()
    {
        $userdata = $this->allUserdata();
        foreach ($userdata as $name => $value) {
            $parts = explode(':new:', $name);
            if (is_array($parts) && count($parts) === 2) {
                $new_name = $this->flashdata_key . ':old:' . $parts[1];
                $this->setUserdata($new_name, $value);
                $this->unsetUserdata($name);
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Removes all flashdata marked as 'old'
     *
     * @access  private
     * @return  void
     */

    private function flashdataSweep()
    {
        $userdata = $this->allUserdata();
        foreach ($userdata as $key => $value) {
            if (strpos($key, ':old:')) {
                $this->unsetUserdata($key);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Get the "now" time
     *
     * @access  private
     * @return  string
     */
    private function getTime()
    {
        if (strtolower($this->time_reference) == 'gmt') {
            $now  = time();
            $time = mktime(
                gmdate("H", $now),
                gmdate("i", $now),
                gmdate("s", $now),
                gmdate("m", $now),
                gmdate("d", $now),
                gmdate("Y", $now)
            );
        } else {
            $time = time();
        }

        return $time;
    }
}
// END Session Class

/* End of file Session.php */
/* Location: ./system/libraries/Session.php */
