<?php
/**
 * Template Name: Checkout Page
 * 
 * @package Beautiful_Rescues
 */

// Ensure we have the minimum required WordPress functions
if (!function_exists('get_header')) {
    require_once(ABSPATH . 'wp-load.php');
}

// Get header if it exists
if (function_exists('get_header')) {
    get_header();
} else {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <div class="site">
    <?php
}

// Start the loop
while (have_posts()) :
    the_post();
    ?>
    <div class="checkout-page">
        <div class="checkout-container">
            <div class="checkout-header">
                <h1><?php the_title(); ?></h1>
                <p><?php _e('Please complete your donation verification below.', 'beautiful-rescues'); ?></p>
            </div>

            <div class="checkout-body">
                <!-- Selected Images Preview -->
                <div class="checkout-column">
                    <div class="selected-images-preview">
                        <h2><?php _e('Selected Images', 'beautiful-rescues'); ?></h2>
                        <div class="selected-images-grid">
                            <!-- Images will be populated via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Donation Verification Form -->
                <div class="checkout-column">
                    <?php 
                    // Get the donation verification instance and render the form
                    if (class_exists('BR_Donation_Verification')) {
                        $verification = new BR_Donation_Verification();
                        echo $verification->render_verification_form(array(
                            'source' => 'checkout',
                            'show_image_upload' => false, // We already have selected images
                            'form_id' => 'checkout-verification-form',
                            'submit_button_text' => __('Complete Checkout', 'beautiful-rescues')
                        ));
                    } else {
                        _e('Verification form is not available.', 'beautiful-rescues');
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
endwhile;

// Get footer if it exists
if (function_exists('get_footer')) {
    get_footer();
} else {
    ?>
    </div><!-- .site -->
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
} 