<?php
/**
 * Main plugin class
 *
 * @package Beautiful_Rescues
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Beautiful Rescues class
 */
class BR_Beautiful_Rescues {
    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = BR_VERSION;

    /**
     * Plugin instance
     *
     * @var BR_Beautiful_Rescues
     */
    private static $instance = null;

    /**
     * Debug instance
     *
     * @var BR_Debug
     */
    private $debug;

    /**
     * Settings instance
     *
     * @var BR_Settings
     */
    private $settings;

    /**
     * Get plugin instance
     *
     * @return BR_Beautiful_Rescues
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Get debug instance
        $this->debug = BR_Debug::get_instance();
        
        
        // Initialize plugin immediately
        $this->init();
    }

    /**
     * Plugin activation hook
     */
    public static function activate() {
        $debug = BR_Debug::get_instance();
        
        // Set default options
        $default_options = array(
            'cloudinary_folder' => 'Cats',
            'allowed_admin_domains' => 'replit.com,21adsmedia.com',
            'max_file_size' => 5
        );

        // Only update if options don't exist
        if (!get_option('beautiful_rescues_options')) {
            add_option('beautiful_rescues_options', $default_options);
        }
        
        // Create checkout page if it doesn't exist
        $checkout_page = get_page_by_path('checkout');
        if (!$checkout_page) {
            $page_data = array(
                'post_title'    => 'Checkout',
                'post_name'     => 'checkout',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_content'  => '',
                'post_author'   => get_current_user_id(),
                'page_template' => 'checkout.php'
            );
            
            $page_id = wp_insert_post($page_data);
            if ($page_id) {
                update_post_meta($page_id, '_wp_page_template', 'checkout.php');
            }
        } else {
            // Update existing checkout page to use our template
            update_post_meta($checkout_page->ID, '_wp_page_template', 'checkout.php');
        }

        // Create review page if it doesn't exist
        $review_page = get_page_by_path('review-donations');
        if (!$review_page) {
            $page_data = array(
                'post_title'    => 'Review Donations',
                'post_name'     => 'review-donations',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_content'  => '[beautiful_rescues_donation_review]',
                'post_author'   => get_current_user_id()
            );
            
            $page_id = wp_insert_post($page_data);
            if ($page_id) {
                update_post_meta($page_id, '_wp_page_template', 'review.php');
                $debug->log('Review page created', array(
                    'page_id' => $page_id,
                    'template' => 'review.php'
                ));
            }
        } else {
            // Update existing review page
            wp_update_post(array(
                'ID' => $review_page->ID,
                'post_content' => '[beautiful_rescues_donation_review]'
            ));
            update_post_meta($review_page->ID, '_wp_page_template', 'review.php');
            $debug->log('Review page updated', array(
                'page_id' => $review_page->ID,
                'template' => 'review.php'
            ));
        }
        
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('beautiful-rescues', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Register post types and taxonomies on init hook with priority 0
        add_action('init', array($this, 'register_post_types'), 0);
        add_action('init', array($this, 'register_taxonomies'), 0);

        // Initialize components after post types are registered
        add_action('init', array($this, 'init_components'), 1);

        // Register shortcodes immediately
        $this->register_shortcodes();

        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Register admin menu after post types are registered (priority 20 to ensure post type exists)
        add_action('admin_menu', array($this, 'register_admin_menu'), 20);

        // Register template hooks
        add_filter('theme_page_templates', array($this, 'add_checkout_template'));
        add_filter('template_include', array($this, 'load_checkout_template'));
        add_filter('single_template', array($this, 'load_donation_template'));
    }

    /**
     * Register post types
     */
    public function register_post_types() {
        // Register verifications post type
        $labels = array(
            'name'               => _x('Verifications', 'post type general name', 'beautiful-rescues'),
            'singular_name'      => _x('Verification', 'post type singular name', 'beautiful-rescues'),
            'menu_name'          => _x('Verifications', 'admin menu', 'beautiful-rescues'),
            'add_new'            => _x('Add New', 'verification', 'beautiful-rescues'),
            'add_new_item'       => __('Add New Verification', 'beautiful-rescues'),
            'edit_item'          => __('Edit Verification', 'beautiful-rescues'),
            'new_item'           => __('New Verification', 'beautiful-rescues'),
            'view_item'          => __('View Verification', 'beautiful-rescues'),
            'search_items'       => __('Search Verifications', 'beautiful-rescues'),
            'not_found'          => __('No verifications found', 'beautiful-rescues'),
            'not_found_in_trash' => __('No verifications found in Trash', 'beautiful-rescues'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // We'll add it as a submenu
            'query_var'           => true,
            'rewrite'             => array('slug' => 'verification'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array('title'),
            'show_in_rest'        => false,
        );

        register_post_type('verification', $args);
        error_log('Registered verification post type');
    }

    /**
     * Register taxonomies
     */
    public function register_taxonomies() {
        // Taxonomies are registered by their respective classes
    }

    /**
     * Register shortcodes
     */
    public function register_shortcodes() {

        add_shortcode('beautiful_rescues_cart', array($this, 'render_cart_shortcode'));
    }

    /**
     * Render cart shortcode
     */
    public function render_cart_shortcode($atts) {
        $this->debug->log('Rendering cart shortcode', array(
            'atts' => $atts,
            'is_admin' => is_admin()
        ), 'info');

        // Parse attributes
        $atts = shortcode_atts(array(
            'style' => 'default'
        ), $atts);

        // Start output buffering
        ob_start();
        ?>
        <div class="beautiful-rescues-cart beautiful-rescues-cart-<?php echo esc_attr($atts['style']); ?>">
            <div class="cart-header">
                <h3><?php _e('Selected Images', 'beautiful-rescues'); ?></h3>
                <span class="cart-count">0</span>
            </div>
            <div class="cart-items">
                <!-- Cart items will be populated via JavaScript -->
            </div>
            <div class="cart-footer">
                <button class="checkout-button" disabled><?php _e('Proceed to Checkout', 'beautiful-rescues'); ?></button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {

        // Enqueue gallery scripts
        wp_enqueue_style(
            'beautiful-rescues-gallery',
            BR_PLUGIN_URL . 'public/css/gallery.css',
            array(),
            BR_VERSION
        );

        wp_enqueue_script(
            'beautiful-rescues-gallery',
            BR_PLUGIN_URL . 'public/js/gallery.js',
            array('jquery'),
            BR_VERSION,
            true
        );

        // Enqueue cart scripts
        wp_enqueue_style(
            'beautiful-rescues-cart',
            BR_PLUGIN_URL . 'public/css/cart.css',
            array(),
            BR_VERSION
        );

        wp_enqueue_script(
            'beautiful-rescues-cart',
            BR_PLUGIN_URL . 'public/js/cart.js',
            array('jquery'),
            BR_VERSION,
            true
        );

        // Check if we're on the checkout page
        $is_checkout_page = get_page_template_slug() === 'checkout.php';

        // Enqueue checkout scripts
        if ($is_checkout_page) {
            $this->debug->log('Enqueuing checkout scripts', array(
                'checkout_css_path' => BR_PLUGIN_URL . 'public/css/checkout.css',
                'checkout_js_path' => BR_PLUGIN_URL . 'public/js/checkout.js',
                'file_exists_css' => file_exists(BR_PLUGIN_DIR . 'public/css/checkout.css'),
                'file_exists_js' => file_exists(BR_PLUGIN_DIR . 'public/js/checkout.js')
            ), 'info');

            wp_enqueue_style(
                'beautiful-rescues-checkout',
                BR_PLUGIN_URL . 'public/css/checkout.css',
                array(),
                BR_VERSION
            );

            wp_enqueue_script(
                'beautiful-rescues-checkout',
                BR_PLUGIN_URL . 'public/js/checkout.js',
                array('jquery'),
                BR_VERSION,
                true
            );

            wp_localize_script('beautiful-rescues-checkout', 'beautifulRescuesCheckout', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('beautiful_rescues_verification_nonce'),
                'homeUrl' => home_url(),
                'watermarkUrl' => get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356531/br-watermark-2025_2x_baljip.webp'),
                'i18n' => array(
                    'noImages' => __('No images selected', 'beautiful-rescues'),
                    'thankYou' => __('Thank you for your donation! We will review your verification and get back to you soon.', 'beautiful-rescues'),
                    'error' => __('An error occurred. Please try again.', 'beautiful-rescues')
                )
            ));
        }

        // Localize gallery script
        wp_localize_script('beautiful-rescues-gallery', 'beautifulRescuesGallery', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_gallery_nonce'),
            'watermarkUrl' => get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356531/br-watermark-2025_2x_baljip.webp'),
            'i18n' => array(
                'select' => __('Select', 'beautiful-rescues'),
                'selected' => __('Selected', 'beautiful-rescues'),
                'zoom' => __('Zoom', 'beautiful-rescues'),
                'noImages' => __('No images selected', 'beautiful-rescues'),
                'verifyDonation' => __('Verify Donation', 'beautiful-rescues'),
                'thankYou' => __('Thank you for your donation! We will review your verification and get back to you soon.', 'beautiful-rescues'),
                'error' => __('An error occurred. Please try again.', 'beautiful-rescues')
            )
        ));

        // Localize cart script
        wp_localize_script('beautiful-rescues-cart', 'beautifulRescuesCart', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_cart_nonce'),
            'checkoutUrl' => home_url('/checkout/'),
            'watermarkUrl' => get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356531/br-watermark-2025_2x_baljip.webp'),
            'i18n' => array(
                'noImagesSelected' => __('No images selected', 'beautiful-rescues'),
                'selectedImages' => __('Selected Images', 'beautiful-rescues'),
                'firstName' => __('First Name', 'beautiful-rescues'),
                'lastName' => __('Last Name', 'beautiful-rescues'),
                'email' => __('Email Address', 'beautiful-rescues'),
                'phone' => __('Phone Number', 'beautiful-rescues'),
                'donationVerification' => __('Donation Verification (Image or PDF)', 'beautiful-rescues'),
                'uploadHelp' => __('Upload a screenshot or PDF of your donation receipt', 'beautiful-rescues'),
                'message' => __('Message (Optional)', 'beautiful-rescues'),
                'completeCheckout' => __('Complete Checkout', 'beautiful-rescues'),
                'thankYou' => __('Thank you for your donation! We will review your verification and get back to you soon.', 'beautiful-rescues'),
                'error' => __('An error occurred. Please try again.', 'beautiful-rescues')
            )
        ));
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        // Add main menu
        add_menu_page(
            __('Beautiful Rescues', 'beautiful-rescues'),
            __('Beautiful Rescues', 'beautiful-rescues'),
            'manage_options',
            'beautiful-rescues',
            array($this, 'render_admin_page'),
            'dashicons-heart',
            20
        );

        // Add Settings submenu
        add_submenu_page(
            'beautiful-rescues',
            __('Beautiful Rescues Settings', 'beautiful-rescues'),
            __('Settings', 'beautiful-rescues'),
            'manage_options',
            'beautiful-rescues-settings',
            array($this, 'render_admin_page')
        );

        // Add Verifications submenu
        add_submenu_page(
            'beautiful-rescues',
            __('Verifications', 'beautiful-rescues'),
            __('Verifications', 'beautiful-rescues'),
            'manage_options',
            'edit.php?post_type=verification'
        );
    }

    /**
     * Render the admin page content
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current page
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        
        // Render different content based on the page
        if ($page === 'beautiful-rescues-settings') {
            $this->settings->render_settings_form();
        } else {
            // Default main page content
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="beautiful-rescues-dashboard">
                    <h2><?php _e('Welcome to Beautiful Rescues', 'beautiful-rescues'); ?></h2>
                    <p><?php _e('Use the menu on the left to manage your rescue verifications and settings.', 'beautiful-rescues'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        $this->debug->log('Initializing components', array(
            'post_type_exists' => post_type_exists('verification'),
            'current_hook' => current_filter()
        ));

        // Initialize settings
        $this->settings = new BR_Settings();
        
        // Initialize Cloudinary integration
        new BR_Cloudinary_Integration();
        
        // Initialize verification post type
        if (post_type_exists('verification')) {
            new BR_Verification_Post_Type();
            $this->debug->log('Initialized verification post type');
        } else {
            $this->debug->log('Verification post type does not exist during component initialization', null, 'error');
        }
        
        // Initialize gallery shortcode
        new BR_Gallery_Shortcode();
        
        // Initialize donation verification
        new BR_Donation_Verification();

        // Initialize donation review
        new BR_Donation_Review();

        // Initialize cart shortcode
        new BR_Cart_Shortcode();
    }

    /**
     * Add checkout template to page templates
     */
    public function add_checkout_template($templates) {

        if (is_array($templates)) {
            // For WordPress page templates
            $templates['checkout.php'] = __('Checkout Page', 'beautiful-rescues');
        }
        return $templates;
    }

    /**
     * Load checkout template
     */
    public function load_checkout_template($template) {

        // Check if we're on a page with our template
        if (is_page() && get_page_template_slug() === 'checkout.php') {
            $new_template = plugin_dir_path(dirname(__FILE__)) . 'templates/checkout.php';
            if (file_exists($new_template)) {
                
                // If Elementor is active and we're in the editor, let Elementor handle it
                if (defined('ELEMENTOR_VERSION') && isset($_GET['elementor-preview'])) {
                    $this->debug->log('In Elementor preview, using original template', null, 'info');
                    return $template;
                }
                return $new_template;
            } else {
                $this->debug->log('Checkout template file not found', array(
                    'template_path' => $new_template,
                    'plugin_dir' => plugin_dir_path(dirname(__FILE__)),
                    'templates_dir' => plugin_dir_path(dirname(__FILE__)) . 'templates/',
                    'dir_exists' => is_dir(plugin_dir_path(dirname(__FILE__)) . 'templates/')
                ), 'error');
            }
        }
        return $template;
    }

    /**
     * Load single donation template
     */
    public function load_donation_template($template) {
        global $post;

        if ($post->post_type === 'verification') {
            $custom_template = BR_PLUGIN_DIR . 'templates/single-verification.php';
            
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts() {
        // Enqueue admin scripts here
    }
} 