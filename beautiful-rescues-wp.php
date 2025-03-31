<?php
/**
* @link              https://github.com/askinne2/beautiful-rescues-wp
* @since             1.0.0
* @package           beautiful_rescues
*
* @wordpress-plugin
* Plugin Name:       Beautiful Rescues
* Plugin URI:        https://github.com/askinne2/beautiful-rescues-wp
*
* Description:       WordPress plugin for managing rescue donations and displaying Cloudinary image galleries
*
*
* Version:           1.0.0
* Author:            21adsmedia
* Author URI:        https://21adsmedia.com
* License:           GPL-2.0+
* License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
* Text Domain:       beautiful-rescues
* Domain Path:       /languages
* GitHub Plugin URI: https://github.com/beautifulrescues/beautiful-rescues-wp

*/
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('BR_VERSION', '1.0.0');
define('BR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize debug early
require_once BR_PLUGIN_DIR . 'includes/class-debug.php';
$debug = BR_Debug::get_instance();

// Check template file existence
$template_file = BR_PLUGIN_DIR . 'templates/checkout.php';
if (file_exists($template_file)) {
    $debug->log('Checkout template file found', array(
        'template_path' => $template_file
    ), 'info');
} else {
    $debug->log('Checkout template file not found', array(
        'template_path' => $template_file
    ), 'error');
}

// Load Composer autoloader
$autoload_file = BR_PLUGIN_DIR . 'vendor/autoload.php';

// Check vendor directory
$vendor_dir = BR_PLUGIN_DIR . 'vendor';

if (file_exists($autoload_file)) {
    require_once $autoload_file;
} else {
    $debug->log('Composer autoloader not found', null, 'error');
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Beautiful Rescues: Composer dependencies not found. Please run "composer install" in the plugin directory.', 'beautiful-rescues'); ?></p>
        </div>
        <?php
    });
    return;
}

// Include required files
require_once BR_PLUGIN_DIR . 'includes/class-beautiful-rescues.php';
require_once BR_PLUGIN_DIR . 'includes/class-settings.php';
require_once BR_PLUGIN_DIR . 'includes/class-cloudinary-integration.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-post-type.php';
require_once BR_PLUGIN_DIR . 'includes/class-gallery-shortcode.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-verification.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-review.php';
require_once BR_PLUGIN_DIR . 'includes/class-cart-shortcode.php';

// Initialize the plugin
function beautiful_rescues_init() {
    $debug = BR_Debug::get_instance();
    $debug->log('Initializing Beautiful Rescues plugin from main file', array(
        'hook' => current_filter(),
        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
    ), 'info');
    
    BR_Beautiful_Rescues::get_instance();
}

// Initialize on plugins_loaded to ensure all WordPress functions are available
add_action('plugins_loaded', 'beautiful_rescues_init', 10);

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('BR_Beautiful_Rescues', 'activate'));
register_deactivation_hook(__FILE__, array('BR_Beautiful_Rescues', 'deactivate'));

// Add admin notice if Cloudinary credentials are missing
add_action('admin_notices', 'beautiful_rescues_check_credentials');
function beautiful_rescues_check_credentials() {
    $options = get_option('beautiful_rescues_options');
    if (empty($options['cloudinary_cloud_name']) || 
        empty($options['cloudinary_api_key']) || 
        empty($options['cloudinary_api_secret'])) {
        ?>
        <div class="notice notice-warning">
            <p><?php _e('Beautiful Rescues: Cloudinary credentials are missing. Please configure them in the plugin settings.', 'beautiful-rescues'); ?></p>
        </div>
        <?php
    }
} 