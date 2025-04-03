<?php
/**
 * Cart Shortcode Class
 */
class BR_Cart_Shortcode {
    public function __construct() {
        add_shortcode('beautiful_rescues_cart', array($this, 'render_cart'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
    }

    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        $debug = BR_Debug::get_instance();
        
        // Add Elementor dependency if Elementor is active
        $dependencies = defined('ELEMENTOR_VERSION') ? array('elementor-frontend') : array();
        
        wp_enqueue_style(
            'beautiful-rescues-cart',
            BR_PLUGIN_URL . 'public/css/cart.css',
            $dependencies,
            BR_VERSION
        );

        wp_enqueue_script(
            'beautiful-rescues-cart',
            BR_PLUGIN_URL . 'public/js/cart.js',
            array('jquery', 'elementor-frontend'),
            BR_VERSION,
            true
        );

        $watermark_url = get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356913/br-watermark-2025_2x_uux1x2.webp');
        
        $debug->log('Cart script localization', [
            'watermark_url' => $watermark_url,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'max_file_size' => (int) (get_option('beautiful_rescues_options')['max_file_size'] ?? 5) * 1024 * 1024
        ]);

        wp_localize_script('beautiful-rescues-cart', 'beautifulRescuesCart', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_cart_nonce'),
            'maxFileSize' => (int) (get_option('beautiful_rescues_options')['max_file_size'] ?? 5) * 1024 * 1024,
            'checkoutUrl' => home_url('/checkout/'),
            'watermarkUrl' => $watermark_url,
            'i18n' => array(
                'checkout' => __('Checkout', 'beautiful-rescues'),
                'clearCart' => __('Clear Cart', 'beautiful-rescues'),
                'noImages' => __('No images selected', 'beautiful-rescues'),
                'imagesSelected' => __('images selected', 'beautiful-rescues'),
                'pleaseReview' => __('Please review your selected images and provide your details.', 'beautiful-rescues'),
                'selectedImages' => __('Selected Images', 'beautiful-rescues'),
                'firstName' => __('First Name', 'beautiful-rescues'),
                'lastName' => __('Last Name', 'beautiful-rescues'),
                'email' => __('Email Address', 'beautiful-rescues'),
                'phone' => __('Phone Number', 'beautiful-rescues'),
                'donationVerification' => __('Donation Verification (Image or PDF)', 'beautiful-rescues'),
                'uploadHelp' => __('Upload a screenshot or PDF of your donation receipt', 'beautiful-rescues'),
                'message' => __('Message (Optional)', 'beautiful-rescues'),
                'completeCheckout' => __('Complete Checkout', 'beautiful-rescues'),
                'thankYou' => __('Thank you for your submission!', 'beautiful-rescues'),
                'error' => __('An error occurred. Please try again.', 'beautiful-rescues')
            )
        ));
    }

    /**
     * Render the cart shortcode
     */
    public function render_cart($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default', // default, compact, icon-only
            'position' => 'right', // left, right, center
            'color' => 'var(--e-global-color-accent)', // Use Elementor's color variable
            'background' => 'var(--e-global-color-background)', // Use Elementor's background variable
            'text_color' => 'var(--e-global-color-text)' // Use Elementor's text color variable
        ), $atts);

        // Add Elementor-specific classes if Elementor is active
        $elementor_classes = defined('ELEMENTOR_VERSION') ? 'elementor-section elementor-section-boxed' : '';
        
        ob_start();
        ?>
        <div class="beautiful-rescues-cart <?php echo esc_attr($elementor_classes); ?>" 
             data-style="<?php echo esc_attr($atts['style']); ?>"
             data-position="<?php echo esc_attr($atts['position']); ?>">
            
            <div class="cart-button">
                <span class="cart-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </span>
                <span class="cart-count">0</span>
                <?php if ($atts['style'] !== 'icon-only'): ?>
                    <span class="cart-text"><?php _e('Checkout', 'beautiful-rescues'); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
} 