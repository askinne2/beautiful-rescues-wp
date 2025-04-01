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
        
        // Initialize plugin on init hook
        add_action('init', array($this, 'init'));
    }

    /**
     * Add checkout template to page templates
     */
    public static function add_checkout_template($templates) {
        if (is_array($templates)) {
            // For WordPress page templates
            $templates['checkout.php'] = __('Checkout Page', 'beautiful-rescues');
        }
        return $templates;
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        $debug = BR_Debug::get_instance();
        $debug->log('Starting Beautiful Rescues activation', null, 'info');

        // Register post types first
        self::register_post_types();
        $debug->log('Post types registered during activation', null, 'info');

        // Register shortcodes
        self::register_shortcodes();
        $debug->log('Shortcodes registered', null, 'info');

        // Register template
        add_filter('theme_page_templates', array('BR_Beautiful_Rescues', 'add_checkout_template'));
        $debug->log('Checkout template registered', null, 'info');

        // Flush rewrite rules after registering post types
        flush_rewrite_rules();
        $debug->log('Rewrite rules flushed', null, 'info');

        // Create default settings
        $settings = new BR_Settings();
        $settings->create_default_settings();
        $debug->log('Default settings created', null, 'info');

        // Set activation flag
        update_option('beautiful_rescues_activated', true);
        $debug->log('Activation completed', null, 'info');
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        $debug = BR_Debug::get_instance();
        $debug->log('Starting Beautiful Rescues deactivation', null, 'info');
        
        // Flush rewrite rules to clean up
        flush_rewrite_rules();
        $debug->log('Rewrite rules flushed during deactivation', null, 'info');
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        $debug = BR_Debug::get_instance();
        $debug->log('Starting Beautiful Rescues initialization', null, 'info');

        // Load text domain
        load_plugin_textdomain('beautiful-rescues', false, dirname(plugin_basename(__FILE__)) . '/languages');
        $debug->log('Text domain loaded', null, 'info');

        // Register post types on every page load
        self::register_post_types();
        $debug->log('Post types registered during init', null, 'info');

        // Register taxonomies
        $this->register_taxonomies();
        $debug->log('Taxonomies registered', null, 'info');

        // Register shortcodes
        self::register_shortcodes();
        $debug->log('Shortcodes registered during init', null, 'info');

        // Initialize components
        $this->init_components();
        $debug->log('Components initialized', null, 'info');

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        $debug->log('Scripts and styles registered', null, 'info');

        // Add admin notice if Cloudinary credentials are missing
        if (is_admin()) {
            add_action('admin_notices', array($this, 'check_cloudinary_credentials'));
            $debug->log('Admin notices registered', null, 'info');
        }

        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        $debug->log('Admin menu registration hook added', null, 'info');

        // Add template filters
        add_filter('single_template', array($this, 'load_donation_template'));
        add_filter('template_include', array($this, 'load_checkout_template'));
        $debug->log('Template filters added', null, 'info');

        $debug->log('Beautiful Rescues initialization completed', null, 'info');
    }

    /**
     * Register post types
     */
    public static function register_post_types() {
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
            'supports'            => array('title', 'editor', 'thumbnail'),
            'show_in_rest'        => false,
            'menu_icon'           => 'dashicons-heart',
            'capabilities'        => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
                'create_posts'       => 'manage_options',
                'delete_posts'       => 'manage_options',
                'delete_others_posts'=> 'manage_options',
            ),
        );

        register_post_type('verification', $args);
        BR_Debug::get_instance()->log('Registered verification post type', null, 'info');
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
    public static function register_shortcodes() {
        $debug = BR_Debug::get_instance();
        
        // Register cart shortcode
        add_shortcode('beautiful_rescues_cart', array('BR_Beautiful_Rescues', 'render_cart_shortcode'));
        $debug->log('Registered cart shortcode', array(
            'shortcode' => 'beautiful_rescues_cart',
            'callback' => array('BR_Beautiful_Rescues', 'render_cart_shortcode')
        ), 'info');
        
        // Create an instance of the gallery shortcode class
        $gallery_shortcode = new BR_Gallery_Shortcode();
        add_shortcode('beautiful_rescues_gallery', array($gallery_shortcode, 'render_gallery'));
        $debug->log('Registered gallery shortcode', array(
            'shortcode' => 'beautiful_rescues_gallery',
            'callback' => array($gallery_shortcode, 'render_gallery')
        ), 'info');
    }

    /**
     * Render cart shortcode
     */
    public static function render_cart_shortcode($atts) {
        $debug = BR_Debug::get_instance();
        $debug->log('Rendering cart shortcode', array(
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

        // Add Elementor integration styles if Elementor is active
        if (defined('ELEMENTOR_VERSION')) {
            wp_enqueue_style(
                'beautiful-rescues-elementor',
                BR_PLUGIN_URL . 'public/css/elementor-integration.css',
                array('elementor-frontend'),
                BR_VERSION
            );
        }

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
        $debug = BR_Debug::get_instance();
        $debug->log('Registering admin menu', null, 'info');

        // Add main menu
        add_menu_page(
            __('Beautiful Rescues', 'beautiful-rescues'),
            __('Beautiful Rescues', 'beautiful-rescues'),
            'manage_options',
            'beautiful-rescues',
            array($this, 'render_admin_page'),
            'dashicons-heart',
            30
        );

        // Add Verifications submenu
        add_submenu_page(
            'beautiful-rescues',
            __('Verifications', 'beautiful-rescues'),
            __('Verifications', 'beautiful-rescues'),
            'manage_options',
            'edit.php?post_type=verification'
        );

        // Add Settings submenu
        add_submenu_page(
            'beautiful-rescues',
            __('Settings', 'beautiful-rescues'),
            __('Settings', 'beautiful-rescues'),
            'manage_options',
            'beautiful-rescues-settings',
            array($this, 'render_admin_page')
        );

        $debug->log('Admin menu registration completed', null, 'info');
    }

    /**
     * Render the admin page content
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current page
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        
        // Create settings instance
        $settings = new BR_Settings();
        
        // Render different content based on the page
        if ($page === 'beautiful-rescues-settings') {
            $settings->render_settings_form();
        } else {
            // Get verification stats
            $total_verifications = wp_count_posts('verification');
            $pending_verifications = count(get_posts(array(
                'post_type' => 'verification',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_status',
                        'value' => 'pending'
                    )
                )
            )));
            $verified_count = count(get_posts(array(
                'post_type' => 'verification',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_status',
                        'value' => 'verified'
                    )
                )
            )));
            
            // Get recent verifications
            $recent_verifications = get_posts(array(
                'post_type' => 'verification',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            ));

            // Get Cloudinary stats
            $cloudinary = new BR_Cloudinary_Integration();
            
            // Get total images count from transient or calculate it
            $total_images = get_transient('br_total_images_count');
            if (false === $total_images) {
                $total_images = $cloudinary->get_total_images_count('Cats');
                set_transient('br_total_images_count', $total_images, HOUR_IN_SECONDS);
            }
            
            // Start output buffering
            ob_start();
            ?>
            <div class="wrap beautiful-rescues-dashboard">                
                <!-- Welcome Section -->
                <div class="br-welcome-section">
                    <div class="br-welcome-content">
                        <h1><?php _e('Welcome to Beautiful Rescues', 'beautiful-rescues'); ?></h1>
                        <p><?php _e('Manage your rescue verifications, review donations, and configure settings from this dashboard.', 'beautiful-rescues'); ?></p>
                    </div>
                    <div class="br-welcome-image">
                        <img src="<?php echo esc_url(BR_PLUGIN_URL . 'admin/images/welcome.webp'); ?>" alt="Beautiful Rescues">
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="br-stats-grid">
                    <div class="br-stat-card">
                        <div class="br-stat-icon">
                            <span class="dashicons dashicons-heart"></span>
                        </div>
                        <div class="br-stat-content">
                            <h3><?php _e('Total Verifications', 'beautiful-rescues'); ?></h3>
                            <p class="br-stat-number"><?php echo esc_html($total_verifications->publish); ?></p>
                        </div>
                    </div>
                    
                    <div class="br-stat-card">
                        <div class="br-stat-icon pending">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="br-stat-content">
                            <h3><?php _e('Pending Reviews', 'beautiful-rescues'); ?></h3>
                            <p class="br-stat-number"><?php echo esc_html($pending_verifications); ?></p>
                        </div>
                    </div>
                    
                    <div class="br-stat-card">
                        <div class="br-stat-icon">
                            <span class="dashicons dashicons-format-gallery"></span>
                        </div>
                        <div class="br-stat-content">
                            <h3><?php _e('Total Images', 'beautiful-rescues'); ?></h3>
                            <p class="br-stat-number"><?php echo esc_html($total_images); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="br-quick-actions">
                    <h3><?php _e('Quick Actions', 'beautiful-rescues'); ?></h3>
                    <div class="br-action-buttons">
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=verification')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Add New Verification', 'beautiful-rescues'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=beautiful-rescues-settings')); ?>" class="button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Configure Settings', 'beautiful-rescues'); ?>
                        </a>
                        <a href="<?php echo esc_url(home_url('/review-donations/')); ?>" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Review Donations', 'beautiful-rescues'); ?>
                        </a>
                    </div>
                </div>

                <!-- Recent Verifications -->
                <div class="br-recent-verifications">
                    <h3><?php _e('Recent Verifications', 'beautiful-rescues'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Title', 'beautiful-rescues'); ?></th>
                                <th><?php _e('Status', 'beautiful-rescues'); ?></th>
                                <th><?php _e('Date', 'beautiful-rescues'); ?></th>
                                <th><?php _e('Actions', 'beautiful-rescues'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_verifications)) : ?>
                                <?php foreach ($recent_verifications as $verification) : 
                                    $status = get_post_meta($verification->ID, '_status', true);
                                    $status_class = $status ? 'status-' . esc_attr($status) : 'status-pending';
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($verification->post_title); ?></td>
                                        <td><span class="br-status <?php echo $status_class; ?>"><?php echo esc_html(ucfirst($status ?: 'pending')); ?></span></td>
                                        <td><?php echo esc_html(get_the_date('', $verification)); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($verification->ID)); ?>" class="button button-small">
                                                <?php _e('Edit', 'beautiful-rescues'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4"><?php _e('No recent verifications found.', 'beautiful-rescues'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- System Status -->
                <div class="br-system-status">
                    <h3><?php _e('System Status', 'beautiful-rescues'); ?></h3>
                    <div class="br-status-grid">
                        <?php
                        // Check Cloudinary credentials
                        $options = get_option('beautiful_rescues_options');
                        $cloudinary_configured = !empty($options['cloudinary_cloud_name']) && 
                                               !empty($options['cloudinary_api_key']) && 
                                               !empty($options['cloudinary_api_secret']);
                        ?>
                        <div class="br-status-item <?php echo $cloudinary_configured ? 'status-ok' : 'status-error'; ?>">
                            <span class="dashicons <?php echo $cloudinary_configured ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
                            <span class="br-status-label"><?php _e('Cloudinary Configuration', 'beautiful-rescues'); ?></span>
                        </div>
                        
                        <?php
                        // Check upload directory
                        $upload_dir = wp_upload_dir();
                        $verification_dir = $upload_dir['basedir'] . '/verifications';
                        $upload_dir_writable = is_writable($verification_dir);
                        ?>
                        <div class="br-status-item <?php echo $upload_dir_writable ? 'status-ok' : 'status-error'; ?>">
                            <span class="dashicons <?php echo $upload_dir_writable ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
                            <span class="br-status-label"><?php _e('Upload Directory', 'beautiful-rescues'); ?></span>
                        </div>
                        
                        <?php
                        // Check rewrite rules
                        $rewrite_rules = get_option('rewrite_rules');
                        $rewrite_rules_ok = !empty($rewrite_rules);
                        ?>
                        <div class="br-status-item <?php echo $rewrite_rules_ok ? 'status-ok' : 'status-error'; ?>">
                            <span class="dashicons <?php echo $rewrite_rules_ok ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
                            <span class="br-status-label"><?php _e('Rewrite Rules', 'beautiful-rescues'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .beautiful-rescues-dashboard {
                    max-width: 1200px;
                    margin: 20px auto;
                }

                .br-welcome-section {
                    display: flex;
                    align-items: center;
                    background: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 30px;
                }

                .br-welcome-content {
                    flex: 1;
                }

                .br-welcome-image {
                    width: 200px;
                    margin-left: 30px;
                }

                .br-welcome-image img {
                    max-width: 100%;
                    height: auto;
                }

                .br-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }

                .br-stat-card {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    display: flex;
                    align-items: center;
                }

                .br-stat-icon {
                    width: 50px;
                    height: 50px;
                    background: #f0f0f0;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                }

                .br-stat-icon .dashicons {
                    font-size: 24px;
                    width: 24px;
                    height: 24px;
                }

                .br-stat-icon.pending {
                    background: #fff3cd;
                    color: #856404;
                }

                .br-stat-number {
                    font-size: 24px;
                    font-weight: bold;
                    margin: 5px 0 0;
                }

                .br-quick-actions {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 30px;
                }

                .br-action-buttons {
                    display: flex;
                    gap: 10px;
                    margin-top: 15px;
                }

                .br-action-buttons .button {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .br-recent-verifications {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 30px;
                }

                .br-status {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 500;
                }

                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                }

                .status-verified {
                    background: #d4edda;
                    color: #155724;
                }

                .status-rejected {
                    background: #f8d7da;
                    color: #721c24;
                }

                .br-system-status {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .br-status-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 15px;
                    margin-top: 15px;
                }

                .br-status-item {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px;
                    border-radius: 4px;
                    background: #f8f9fa;
                }

                .br-status-item.status-ok {
                    color: #155724;
                }

                .br-status-item.status-error {
                    color: #721c24;
                }

                .br-status-item .dashicons {
                    font-size: 20px;
                    width: 20px;
                    height: 20px;
                }
            </style>
            <?php
            echo ob_get_clean();
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
        $this->debug->log('Initialized gallery shortcode');
        
        // Initialize donation verification
        BR_Donation_Verification::get_instance();

        // Initialize donation review
        new BR_Donation_Review();

        // Initialize cart shortcode
        new BR_Cart_Shortcode();
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

    /**
     * Add admin notice if Cloudinary credentials are missing
     */
    public function check_cloudinary_credentials() {
        // Implementation of the method
    }
} 