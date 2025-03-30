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
require_once BR_PLUGIN_DIR . 'includes/class-settings.php';
require_once BR_PLUGIN_DIR . 'includes/class-cloudinary-integration.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-post-type.php';
require_once BR_PLUGIN_DIR . 'includes/class-gallery-shortcode.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-verification.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-review.php';

// Initialize the plugin
function beautiful_rescues_init() {
    $debug = BR_Debug::get_instance();
    
    // Initialize settings
    new BR_Settings();
    
    // Initialize Cloudinary integration
    new BR_Cloudinary_Integration();
    
    // Initialize donation post type
    new BR_Donation_Post_Type();
    
    // Initialize gallery shortcode
    new BR_Gallery_Shortcode();
    
    // Initialize donation verification
    new BR_Donation_Verification();

    // Initialize donation review
    new BR_Donation_Review();
}
add_action('plugins_loaded', 'beautiful_rescues_init');

// Activation hook
register_activation_hook(__FILE__, 'beautiful_rescues_activate');
function beautiful_rescues_activate() {
    // Set default options
    $default_options = array(
        'cloudinary_cloud_name' => 'dgnb4yyrc',
        'cloudinary_api_key' => '835695449949662',
        'cloudinary_api_secret' => 'KN1e_kOwNGV9cl43P3pt5n9rH60',
        'cloudinary_folder' => 'receipts',
        'allowed_admin_domains' => 'replit.com,21adsmedia.com',
        'max_file_size' => 5
    );

    add_option('beautiful_rescues_options', $default_options);
    
    // Create necessary database tables
    // Set up default options
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'beautiful_rescues_deactivate');
function beautiful_rescues_deactivate() {
    flush_rewrite_rules();
} 