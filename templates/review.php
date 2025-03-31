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

// Check user permissions
if (!current_user_can('edit_posts')) {
    wp_redirect(home_url());
    exit;
}

// Get header
get_header();
?>

<div class="wrap">
    <div id="primary" class="content-area">
        <main id="main" class="site-main">
            <?php
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
            ?>
        </main>
    </div>
</div>

<?php
get_footer();
?> 