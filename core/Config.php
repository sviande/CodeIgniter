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
 * CodeIgniter Config Class
 *
 * This class contains functions that enable config files to be managed
 *
 * @package    CodeIgniter
 * @subpackage  Libraries
 * @category  Libraries
 * @author    ExpressionEngine Dev Team
 * @link    http://codeigniter.com/user_guide/libraries/config.html
 */
class Config
{

    /**
     * List of all loaded config values
     *
     * @var array
     */
    public $config = array();
    /**
     * List of all loaded config files
     *
     * @var array
     */
    public $is_loaded = array();
    /**
     * List of paths to search when trying to load a config file
     *
     * @var array
     */
    public $config_paths = array(APPPATH);

    /**
     * Constructor
     *
     * Sets the $config data from the primary config.php file as a class variable
     *
     * @access   public
     */
    public function __construct()
    {
        $this->config =& get_config();
        log_message('debug', "Config Class Initialized");

        // Set the base_url automatically if none was provided
        if ($this->config['base_url'] == '') {
            if (isset($_SERVER['HTTP_HOST'])) {
                $base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
                $base_url .= '://' . $_SERVER['HTTP_HOST'];
                $base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
            } else {
                $base_url = 'http://localhost/';
            }

            $this->setItem('base_url', $base_url);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Load Config File
     *
     * @access public
     * @param string  $file the config file name
     * @param boolean  $use_sections if configuration values should be loaded into their own section
     * @param boolean $fail_gracefully true if errors should return false, false if an error message should be displayed
     * @return boolean  if the file was loaded correctly
     */
    public function load($file = '', $use_sections = false, $fail_gracefully = false)
    {
        $file   = ($file == '') ? 'config' : str_replace('.php', '', $file);
        $found  = false;
        $loaded = false;

        $check_locations = defined('ENVIRONMENT')
          ? array(ENVIRONMENT . '/' . $file, $file)
          : array($file);

        foreach ($this->config_paths as $path) {
            foreach ($check_locations as $location) {
                $file_path = $path . 'config/' . $location . '.php';

                if (in_array($file_path, $this->is_loaded, true)) {
                    $loaded = true;
                    continue 2;
                }

                if (file_exists($file_path)) {
                    $found = true;
                    break;
                }
            }

            if ($found === false) {
                continue;
            }

            $config = null;
            include($file_path);

            if (!is_array($config)) {
                if ($fail_gracefully === true) {
                    return false;
                }
                show_error('Your ' . $file_path . ' file does not appear to contain a valid configuration array.');
            }

            if ($use_sections === true) {
                if (isset($this->config[$file])) {
                    $this->config[$file] = array_merge($this->config[$file], $config);
                } else {
                    $this->config[$file] = $config;
                }
            } else {
                $this->config = array_merge($this->config, $config);
            }

            $this->is_loaded[] = $file_path;
            unset($config);

            $loaded = true;
            log_message('debug', 'Config file loaded: ' . $file_path);
            break;
        }

        if ($loaded === false) {
            if ($fail_gracefully === true) {
                return false;
            }
            show_error('The configuration file ' . $file . '.php does not exist.');
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a config file item
     *
     *
     * @access  public
     * @param  string  $item the config item name
     * @param  string $index the index name
     * @param  bool
     * @return  string
     */
    public function item($item, $index = '')
    {
        if ($index == '') {
            if (!isset($this->config[$item])) {
                return false;
            }

            $pref = $this->config[$item];
        } else {
            if (!isset($this->config[$index])) {
                return false;
            }

            if (!isset($this->config[$index][$item])) {
                return false;
            }

            $pref = $this->config[$index][$item];
        }

        return $pref;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a config file item - adds slash after item (if item is not empty)
     *
     * @access  public
     * @param  string  $item the config item name
     * @param  bool
     * @return  string
     */
    public function slashItem($item)
    {
        if (!isset($this->config[$item])) {
            return false;
        }
        if (trim($this->config[$item]) == '') {
            return '';
        }

        return rtrim($this->config[$item], '/') . '/';
    }

    // --------------------------------------------------------------------

    /**
     * Site URL
     * Returns base_url . index_page [. uriString]
     *
     * @access  public
     * @param  string $uri the URI string
     * @return  string
     */
    public function siteUrl($uri = '')
    {
        if ($uri == '') {
            return $this->slashItem('base_url') . $this->item('index_page');
        }

        if ($this->item('enable_query_strings') == false) {
            $suffix = ($this->item('url_suffix') == false) ? '' : $this->item('url_suffix');
            return $this->slashItem('base_url') . $this->slashItem('index_page') . $this->uriString($uri) . $suffix;
        } else {
            return $this->slashItem('base_url') . $this->item('index_page') . '?' . $this->uriString($uri);
        }
    }

    // -------------------------------------------------------------

    /**
     * Base URL
     * Returns base_url [. uriString]
     *
     * @access public
     * @param string $uri
     * @return string
     */
    public function baseUrl($uri = '')
    {
        return $this->slashItem('base_url') . ltrim($this->uriString($uri), '/');
    }

    // -------------------------------------------------------------

    /**_u
     * Build URI string for use in Config::siteUrl() and Config::base_url()
     *
     * @access protected
     * @param  string $uri
     * @return string
     */
    protected function uriString($uri)
    {
        if ($this->item('enable_query_strings') == false) {
            if (is_array($uri)) {
                $uri = implode('/', $uri);
            }
            $uri = trim($uri, '/');
        } else {
            if (is_array($uri)) {
                $i   = 0;
                $str = '';
                foreach ($uri as $key => $val) {
                    $prefix = ($i == 0) ? '' : '&';
                    $str .= $prefix . $key . '=' . $val;
                    $i++;
                }
                $uri = $str;
            }
        }
        return $uri;
    }

    // --------------------------------------------------------------------

    /**
     * System URL
     *
     * @access  public
     * @return  string
     */
    public function systemUrl()
    {
        $x = explode("/", preg_replace("|/*(.+?)/*$|", "\\1", BASEPATH));
        return $this->slashItem('base_url') . end($x) . '/';
    }

    // --------------------------------------------------------------------

    /**
     * Set a config file item
     *
     * @access  public
     * @param  string $item the config item key
     * @param  string $value the config item value
     * @return  void
     */
    public function setItem($item, $value)
    {
        $this->config[$item] = $value;
    }

    // --------------------------------------------------------------------

    /**
     * Assign to Config
     *
     * This function is called by the front controller (CodeIgniter.php)
     * after the Config class is instantiated.  It permits config items
     * to be assigned or overriden by variables contained in the index.php file
     *
     * @access  private
     * @param  array
     * @return  void
     */
    public function assignToConfig($items = array())
    {
        if (is_array($items)) {
            foreach ($items as $key => $val) {
                $this->setItem($key, $val);
            }
        }
    }
}

// END CI_Config class

/* End of file Config.php */
/* Location: ./system/core/Config.php */
