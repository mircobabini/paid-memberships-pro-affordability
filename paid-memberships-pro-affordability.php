<?php
/**
 * Plugin Name: Paid Memberships Pro Affordability
 *
 * Description: Defines affordability for downloadable Files placing levels on Pages and single Files
 *
 * Plugin URI: 
 * Version: 1.0.2
 *
 * Author: Mirco Babini
 * Author URI: http://mircobabini.com/
 * License: GPLv2
 * @package paid-memberships-pro-affordability
 */

/**
 * The instantiated version of this plugin's class
 */
$GLOBALS['paid_memberships_pro_affordability'] = new paid_memberships_pro_affordability_plugin;

/**
 * Paid Memberships Pro Affordability
 *
 * @package paid-memberships-pro-affordability
 * @link http://github.com/mirkolofio/paid-memberships-pro-affordability
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @author Mirco Babini <mirkolofio@gmail.com>
 * @copyright Mirco Babini, 2013
 *
 * Defines affordability for downloadable Files placing levels on Pages and single Files
 */
class paid_memberships_pro_affordability_plugin {
	/**
	 * This plugin's identifier
	 */
	const ID = 'paid-memberships-pro-affordability';

	/**
	 * This plugin's name
	 */
	const NAME = 'Paid Memberships Pro Affordability';
	const MENU_NAME = '';

	/**
	 * This plugin's version
	 */
	const VERSION = '1.0.2';

	/**
	 * This plugin's table name prefix
	 * @var string
	 */
	protected $prefix = 'paid_memberships_pro_affordability';

	/**
	 * Has the internationalization text domain been loaded?
	 * @var bool
	 */
	protected $loaded_textdomain = false;

	/**
	 * This plugin's options
	 *
	 * Options from the database are merged on top of the default options.
	 *
	 * @see oop_plugin_template_solution::set_options()  to obtain the saved
	 *      settings
	 * @var array
	 */
	protected $options = array();

	/**
	 * This plugin's default options
	 * @var array
	 */
	protected $options_default = array(
		'deactivate_deletes_data' => 1,
		'example_int' => 5,
		'example_string' => '',
		'track_logins' => 1,
	);

	/**
	 * Our option name for storing the plugin's settings
	 * @var string
	 */
	protected $option_name;


	/**
	 * Declares the WordPress action and filter callbacks
	 *
	 * @return void
	 * @uses oop_plugin_template_solution::initialize()  to set the object's
	 *       properties
	 */
	public function __construct() {
		$this->initialize();

		if (is_admin()) {
			$this->load_plugin_textdomain();

			require_once dirname(__FILE__) . '/admin.php';
			$admin = new paid_memberships_pro_affordability_plugin_admin;

			if (is_multisite()) {
				$admin_menu = 'network_admin_menu';
				$admin_notices = 'network_admin_notices';
				$plugin_action_links = 'network_admin_plugin_action_links_' . self::ID . '/' . self::ID . '.php';
			} else {
				$admin_menu = 'admin_menu';
				$admin_notices = 'admin_notices';
				$plugin_action_links = 'plugin_action_links_' . self::ID . '/' . self::ID . '.php';
			}

			add_action($admin_menu, array(&$admin, 'admin_menu'));
			add_action('admin_init', array(&$admin, 'admin_init'));
			add_filter($plugin_action_links, array(&$admin, 'plugin_action_links'));

			register_activation_hook(__FILE__, array(&$admin, 'activate'));
			if ($this->options['deactivate_deletes_data']) {
				register_deactivation_hook(__FILE__, array(&$admin, 'deactivate'));
			}

			add_action('admin_init', array(&$this, 'add_securefile_button'));
		}
		
		add_shortcode ( 'securefile', array ($this, 'do_securefile') );
		require_once ('include/acf-pmp/acf-pmp.php');
		add_filter ('the_title', array ($this, 'the_title'));

		function set_newuser_cookie() {
			if (!isset($_COOKIE['localphone'])) {
			$localphone = get_post_meta($post->ID, "phone", true);
				setcookie('localphone', $localphone, time()+3600);
			}
		}
		
		$cookiekey = 'redirect_to_bought';
		
		// best hook to perform a redirect
		add_action( 'template_redirect', function () use ($cookiekey) {
			if (is_page (pmpro_getOption("checkout_page_id"))) {
				$level = @$_GET['level'];
				
				if (pmpro_hasMembershipLevel ($level)) {
					if (isset ($_COOKIE[$cookiekey])) {
						wp_safe_redirect ($_COOKIE[$cookiekey]);
					} else {
						wp_safe_redirect ('/');
					}
				}
				
				if (isset ($_GET[$cookiekey])) {
					setcookie ($cookiekey, $_GET[$cookiekey], time()+3600, COOKIEPATH, COOKIE_DOMAIN, false);
				}
			} else if (is_page (pmpro_getOption("levels_page_id"))) {
				if (isset ($_COOKIE[$cookiekey])) {
					wp_safe_redirect ($_COOKIE[$cookiekey]);
				} else {
					wp_safe_redirect ('/');
				}
			}
		});

	}
	
	
	function add_securefile_button () {  
	   if ( current_user_can('edit_posts') &&  current_user_can('edit_pages') )  
	   {  
		 add_filter('mce_external_plugins', array ($this, 'add_securefile_plugin'));  
		 add_filter('mce_buttons', array ($this, 'register_securefile_button'));  
	   }  
	}
	function register_securefile_button($buttons) {  
		array_push($buttons, "securefile");  
		return $buttons;  
	} 
	function add_securefile_plugin($plugin_array) {
		if(function_exists( 'wp_enqueue_media' )){
			wp_enqueue_media();
		}else{
			wp_enqueue_style('thickbox');
			wp_enqueue_script('media-upload');
			wp_enqueue_script('thickbox');
		}

		$plugin_array['securefile'] = plugins_url ('js/securefile.js', plugin_basename (__FILE__));  
	    return $plugin_array;  
	}  
	/**
	 * Sets the object's properties and options
	 *
	 * This is separated out from the constructor to avoid undesirable
	 * recursion.  The constructor sometimes instantiates the admin class,
	 * which is a child of this class.  So this method permits both the
	 * parent and child classes access to the settings and properties.
	 *
	 * @return void
	 *
	 * @uses oop_plugin_template_solution::set_options()  to replace the default
	 *       options with those stored in the database
	 */
	protected function initialize() {
		global $wpdb;

		$this->table_login = $wpdb->get_blog_prefix(0) . $this->prefix . 'login';

		$this->option_name = self::ID . '-options';
		$this->umk_login_time = self::ID . '-login-time';

		$this->set_options();
	}

	/**
	 * Sanitizes output via htmlspecialchars() using UTF-8 encoding
	 *
	 * Makes this program's native text and translated/localized strings
	 * safe for displaying in browsers.
	 *
	 * @param string $in   the string to sanitize
	 * @return string  the sanitized string
	 */
	protected function hsc_utf8($in) {
		return htmlspecialchars($in, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * A centralized way to load the plugin's textdomain for
	 * internationalization
	 * @return void
	 */
	protected function load_plugin_textdomain() {
		if (!$this->loaded_textdomain) {
			load_plugin_textdomain(self::ID, false, self::ID . '/languages');
			$this->loaded_textdomain = true;
		}
	}


	/**
	 * Replaces all whitespace characters with one space
	 * @param string $in  the string to clean
	 * @return string  the cleaned string
	 */
	protected function sanitize_whitespace($in) {
		return preg_replace('/\s+/u', ' ', $in);
	}


	/**
	 * Replaces the default option values with those stored in the database
	 * @uses login_security_solution::$options  to hold the data
	 */
	protected function set_options() {
		if (is_multisite()) {
			switch_to_blog(1);
			$options = get_option($this->option_name);
			restore_current_blog();
		} else {
			$options = get_option($this->option_name);
		}
		if (!is_array($options)) {
			$options = array();
		}
		$this->options = array_merge($this->options_default, $options);
	}
	
	
	public function do_securefile ($atts, $content = "") {
		extract( shortcode_atts( array(
			 'id' => false
		), $atts ) );
		
		if ($id === false)
			return '';

		$attachment = get_post ($id);
		if (!$attachment)
			return '';
		
		$securefile = '';
		if (trim ($content == "")) {
			$content = $attachment->post_title;
		}

		$attachment_url = wp_get_attachment_url ($id);

		$levels = array ();
		$level = $this->get_media_level ($id);
		if ($level !== null) {
			require_once 'include/wp-parents.php';
			$levels[] = $level;
			
			$parents_ids = get_parents_ids ($id);
			foreach ($parents_ids as $parent_id) {
				$parent_level = $this->get_page_level ($parent_id);
				if ($parent_level !== null) {
					$levels[] = $parent_level;
				}
			}

			if (!pmpro_hasMembershipLevel ($levels)) {
				$securefile .= '<a href="/membership-account/membership-checkout/?level='.$level.'&redirect_to_bought='.$_SERVER['REQUEST_URI'].'" style="color: inherit">';
				$securefile .= $content;
				$securefile .= '</a>';
			} else {
				$securefile .= '<a href="'.$attachment_url.'" style="color: inherit">';
				$securefile .= $content;
				$securefile .= '</a>';
			}
		}
		
		return $securefile;
	}

	
	function the_title ($title) {
		if (!is_admin () && is_page () && in_the_loop ()) {

			$levels = array ();
			$level = $this->get_page_level ();
			if ($level !== null) {
				require_once 'include/wp-parents.php';
				$levels[] = $level;

				$parents_ids = get_parents_ids ();
				foreach ($parents_ids as $parent_id) {
					$parent_level = $this->get_page_level ($parent_id);
					if ($parent_level !== null) {
						$levels[] = $parent_level;
					}
				}
				
				if (!pmpro_hasMembershipLevel ($levels)) {
					$title .= '<a href="/membership-account/membership-checkout/?level='.$level.'&redirect_to_bought='.$_SERVER['REQUEST_URI'].'" style="float: right">';
					$title .= ' <button class="btn btn-primary">Acquista</button>';
					$title .= '</a>';
				}
			}

		} else {

		}

		return $title;
	}
	
	function get_page_level ($post_id = null) {
		if ($post_id === null) {
			$post_id = get_the_ID ();
		}
		
		$level = get_field ('page-level', $post_id);
		return ($level != "null" && $level !== false) ? $level : null;
	}
	function get_media_level ($post_id = null) {
		if ($post_id === null) {
			$post_id = get_the_ID ();
		}
		
		$level = get_field ('media-level', $post_id);
		return ($level != "null" && $level !== false) ? $level : null;
	}
}

