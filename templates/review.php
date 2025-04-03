<?php
/**
 * Template Name: Donation Review Page
 * 
 * @package Beautiful_Rescues
 */

// Ensure we have the minimum required WordPress functions
if (!function_exists('get_header')) {
    require_once(ABSPATH . 'wp-load.php');
}

// Get header
get_header();
?>

<div class="wrap">
    <div id="primary" class="content-area">
        <main id="main" class="site-main">
            <?php
            // Check user permissions
            if (!current_user_can('edit_posts')) {
                // Show login form instead of redirecting
                ?>
                <div class="donation-review-login-container">
                    <h1>Donation Review Login</h1>
                    <p>Please log in with your administrator account to review donations.</p>
                    <?php
                    // Get the current URL to redirect back after login
                    $redirect_to = esc_url($_SERVER['REQUEST_URI']);
                    
                    // Display the login form
                    $args = array(
                        'redirect' => $redirect_to,
                        'form_id' => 'donation-review-login-form',
                        'label_username' => __('Username'),
                        'label_password' => __('Password'),
                        'label_remember' => __('Remember Me'),
                        'label_log_in' => __('Log In'),
                        'remember' => true,
                    );
                    
                    // Remove the "Register" link from the login form
                    add_filter('login_form_bottom', function($html) {
                        return preg_replace('/<p class="register">.*?<\/p>/', '', $html);
                    });
                    
                    // Display the login form
                    wp_login_form($args);
                    ?>
                </div>
                <?php
            } else {
                // User has permission, show the review page
                while (have_posts()) :
                    the_post();
                    ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                        <div class="entry-content">
                            <?php
                            // Output the shortcode
                            echo do_shortcode('[beautiful_rescues_donation_review]');
                            ?>
                        </div>
                    </article>
                    <?php
                endwhile;
            }
            ?>
        </main>
    </div>
</div>

<?php
get_footer();
?> 