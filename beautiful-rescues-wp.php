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

// Initialize debug as early as possible
require_once dirname(__FILE__) . '/includes/class-debug.php';
$debug = BR_Debug::get_instance();
$debug->expose_debug_state();
$debug->log('Plugin file loaded', array('file' => __FILE__), 'debug');

// Define plugin constants
define('BR_VERSION', '1.0.0');
define('BR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BR_PLUGIN_URL', plugin_dir_url(__FILE__));


// Log plugin loading
$debug->log('Beautiful Rescues plugin file loaded', array(
    'plugin_dir' => BR_PLUGIN_DIR,
    'plugin_url' => BR_PLUGIN_URL,
    'version' => BR_VERSION
), 'debug');

// Check template file existence
$template_file = BR_PLUGIN_DIR . 'templates/checkout.php';
if (!file_exists($template_file)) {
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
    $debug->log('Composer autoloader loaded', null, 'debug');
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
require_once BR_PLUGIN_DIR . 'includes/class-verification-post-type.php';
require_once BR_PLUGIN_DIR . 'includes/class-cloudinary-integration.php';
require_once BR_PLUGIN_DIR . 'includes/class-gallery-shortcode.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-verification.php';
require_once BR_PLUGIN_DIR . 'includes/class-donation-review.php';
require_once BR_PLUGIN_DIR . 'includes/class-cart-shortcode.php';

$debug->log('All required files included', null, 'debug');

// Initialize the plugin
function beautiful_rescues_init() {
    $debug = BR_Debug::get_instance();
    $debug->log('Initializing Beautiful Rescues plugin', null, 'info');
    
    // Enqueue debug utility script
    wp_enqueue_script(
        'beautiful-rescues-debug',
        BR_PLUGIN_URL . 'public/js/debug.js',
        array(),
        BR_VERSION,
        true
    );
    
    BR_Beautiful_Rescues::get_instance();
}

// Initialize on plugins_loaded to ensure all WordPress functions are available
add_action('plugins_loaded', 'beautiful_rescues_init', 10);

// Register activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    // Ensure debug is initialized first
    require_once dirname(__FILE__) . '/includes/class-debug.php';
    $debug = BR_Debug::get_instance();
    $debug->enable();
    $debug->set_log_level('debug');
    
    $debug->log('Beautiful Rescues plugin activation hook triggered', null, 'debug');
    $debug->log('Starting Beautiful Rescues activation process', null, 'info');
    
    try {
        BR_Beautiful_Rescues::activate();
        $debug->log('Beautiful Rescues activation completed successfully', null, 'info');
    } catch (Exception $e) {
        $debug->log('Error during Beautiful Rescues activation: ' . $e->getMessage(), null, 'error');
    }
});

register_deactivation_hook(__FILE__, function() {
    $debug = BR_Debug::get_instance();
    $debug->log('Beautiful Rescues plugin deactivation hook triggered', null, 'debug');
    
    // Force debug mode during deactivation
    $debug->enable();
    $debug->set_log_level('debug');
    
    $debug->log('Starting Beautiful Rescues deactivation process', null, 'info');
    
    try {
        BR_Beautiful_Rescues::deactivate();
        $debug->log('Beautiful Rescues deactivation completed successfully', null, 'info');
    } catch (Exception $e) {
        $debug->log('Error during Beautiful Rescues deactivation: ' . $e->getMessage(), null, 'error');
    }
});

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

// Add Elementor integration styles
if (defined('ELEMENTOR_VERSION')) {
    add_action('wp_enqueue_scripts', function() {
        wp_enqueue_style(
            'beautiful-rescues-elementor',
            BR_PLUGIN_URL . 'public/css/elementor-integration.css',
            array('elementor-frontend'),
            BR_VERSION
        );
    });
} 