<?php
/**
 * Handles donation review functionality
 *
 * @package Beautiful_Rescues
 */

defined('ABSPATH') || exit;

/**
 * Class BR_Donation_Review
 */
class BR_Donation_Review {
    private $debug;

    public function __construct() {
        $this->debug = BR_Debug::get_instance();
        add_action('init', array($this, 'register_endpoints'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('beautiful_rescues_donation_review', array($this, 'render_review_page'));
        add_action('wp_ajax_load_donations', array($this, 'ajax_load_donations'));
        add_action('wp_ajax_get_donation_details', array($this, 'ajax_get_donation_details'));
        add_action('wp_ajax_update_donation_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_update_donation_status', array($this, 'ajax_update_donation_status'));
    }

    /**
     * Register custom endpoints
     */
    public function register_endpoints() {
        add_rewrite_endpoint('review-donations', EP_ROOT);
        add_rewrite_rule(
            'review-donations/?$',
            'index.php?review_donations=1',
            'top'
        );
        add_rewrite_tag('%review_donations%', '([^&]+)');
    }

    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        // Debug log the current page
        $this->debug->log('Checking if we should enqueue review scripts', [
            'is_page' => is_page(),
            'is_singular' => is_singular(),
            'current_url' => $_SERVER['REQUEST_URI'] ?? '',
            'post_content' => get_post()->post_content ?? ''
        ]);

        // Check if we're on the review page either by shortcode or URL
        global $post;
        $should_enqueue = false;

        if (is_page()) {
            // Check for shortcode
            if (has_shortcode($post->post_content, 'beautiful_rescues_donation_review')) {
                $should_enqueue = true;
            }
            // Check for review-donations slug
            if (strpos($_SERVER['REQUEST_URI'], 'review-donations') !== false) {
                $should_enqueue = true;
            }
        }

        if (!$should_enqueue) {
            return;
        }

        $this->debug->log('Enqueuing review scripts and styles');

        wp_enqueue_style(
            'beautiful-rescues-review',
            BR_PLUGIN_URL . 'public/css/review.css',
            array(),
            BR_VERSION
        );

        wp_enqueue_script(
            'beautiful-rescues-review',
            BR_PLUGIN_URL . 'public/js/review.js',
            array('jquery'),
            BR_VERSION,
            true
        );

        // Get the upload directory URL
        $upload_dir = wp_upload_dir();
        $verification_url = $upload_dir['baseurl'] . '/donation-verifications/';

        wp_localize_script('beautiful-rescues-review', 'beautifulRescuesReview', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_review_nonce'),
            'verificationUrl' => $verification_url
        ));

        $this->debug->log('Review scripts and styles enqueued', [
            'css_url' => BR_PLUGIN_URL . 'public/css/review.css',
            'js_url' => BR_PLUGIN_URL . 'public/js/review.js',
            'ajaxurl' => admin_url('admin-ajax.php'),
            'verification_url' => $verification_url,
            'has_nonce' => !empty(wp_create_nonce('beautiful_rescues_review_nonce'))
        ]);
    }

    /**
     * Render the review page
     */
    public function render_review_page() {
        // Debug log the current user and capabilities
        $this->debug->log('Current user', [
            'id' => get_current_user_id(),
            'can_edit_posts' => current_user_can('edit_posts')
        ]);

        if (!current_user_can('edit_posts')) {
            return '<p>You do not have permission to view this page.</p>';
        }

        ob_start();
        ?>
        <div class="donation-review-container">
            <!-- Left Panel - Donation List -->
            <div class="donation-list-panel">
                <div class="donation-review-header">
                    <h1>Donation Review</h1>
                    <div class="review-filters">
                        <select id="status-filter" class="status-filter">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <input type="text" id="search-filter" class="search-filter" placeholder="Search by name or email...">
                    </div>
                </div>
                <div class="donation-list"></div>
                <div class="pagination"></div>
            </div>

            <!-- Right Panel - Donation Details -->
            <div class="donation-details-panel empty">
                <div class="donation-details-content">
                    <div class="donor-info">
                        <div class="donor-details">
                            <h3>Donor Information</h3>
                            <p><strong>Name:</strong> <span class="donor-name"></span></p>
                            <p><strong>Email:</strong> <span class="donor-email"></span></p>
                            <p><strong>Phone:</strong> <span class="donor-phone"></span></p>
                            <p><strong>Message:</strong> <span class="donor-message"></span></p>
                        </div>
                    </div>
                    <div class="verification-file"></div>
                    <div class="selected-images"></div>
                    <div class="donation-actions">
                        <button class="verify-button">Verify</button>
                        <button class="reject-button">Reject</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $output = ob_get_clean();
        
        // Debug log the rendered HTML
        $this->debug->log('Rendered review page HTML', $output);
        
        return $output;
    }

    /**
     * AJAX handler for loading donations
     */
    public function ajax_load_donations() {
        check_ajax_referer('beautiful_rescues_review_nonce', 'nonce');

        // Debug log the incoming request
        $this->debug->log('Donation review AJAX request received', $_POST);

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $status = sanitize_text_field($_POST['status'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        // Debug log the query parameters
        $this->debug->log('Query parameters', [
            'status' => $status,
            'search' => $search,
            'page' => $page,
            'per_page' => $per_page
        ]);

        $args = array(
            'post_type' => 'verification',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        // Add status filter if specified
        if ($status && $status !== 'all') {
            $args['meta_query'][] = array(
                'key' => '_status',
                'value' => $status
            );
        }

        // Add search filter if specified
        if ($search) {
            $args['meta_query'][] = array(
                'relation' => 'OR',
                array(
                    'key' => '_first_name',
                    'value' => $search,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_last_name',
                    'value' => $search,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_email',
                    'value' => $search,
                    'compare' => 'LIKE'
                )
            );
        }

        // Debug log the query arguments
        $this->debug->log('WP_Query arguments', $args);

        $query = new WP_Query($args);

        // Debug log the query results
        $this->debug->log('WP_Query results', [
            'found_posts' => $query->found_posts,
            'post_count' => $query->post_count,
            'have_posts' => $query->have_posts()
        ]);

        $donations = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Debug log each donation's meta data
                $this->debug->log('Processing donation ID: ' . $post_id);
                $this->debug->log('Donation meta', [
                    'first_name' => get_post_meta($post_id, '_first_name', true),
                    'last_name' => get_post_meta($post_id, '_last_name', true),
                    'email' => get_post_meta($post_id, '_email', true),
                    'status' => get_post_meta($post_id, '_status', true)
                ]);

                $donations[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'date' => get_the_date(),
                    'time' => get_the_time('g:i A'),
                    'donor_name' => sprintf(
                        '%s %s',
                        get_post_meta($post_id, '_first_name', true),
                        get_post_meta($post_id, '_last_name', true)
                    ),
                    'email' => get_post_meta($post_id, '_email', true),
                    'status' => get_post_meta($post_id, '_status', true) ?: 'pending',
                    'verification_file' => get_post_meta($post_id, '_verification_file', true),
                    'selected_images' => get_post_meta($post_id, '_selected_images', true)
                );
            }
        }

        wp_reset_postdata();

        // Debug log the final response
        $this->debug->log('Sending response with ' . count($donations) . ' donations');
        $this->debug->log('Response data', $donations);

        wp_send_json_success(array(
            'donations' => $donations,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page
        ));
    }

    /**
     * AJAX handler for getting donation details
     */
    public function ajax_get_donation_details() {
        check_ajax_referer('beautiful_rescues_review_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $donation_id = intval($_POST['donation_id']);
        $donation = get_post($donation_id);

        if (!$donation || $donation->post_type !== 'verification') {
            wp_send_json_error('Invalid verification ID');
        }

        $this->debug->log('Getting verification details for ID: ' . $donation_id);

        // Get the verification file URL
        $verification_file = get_post_meta($donation_id, '_verification_file', true);
        $verification_file_url = get_post_meta($donation_id, '_verification_file_url', true);
        
        // If no URL is stored, construct it
        if (!$verification_file_url && $verification_file) {
            $upload_dir = wp_upload_dir();
            $verification_file_url = str_replace('http://', 'https://', $upload_dir['baseurl']) . '/verifications/' . $verification_file;
        }

        // Get selected images and ensure HTTPS URLs
        $selected_images = get_post_meta($donation_id, '_selected_images', true);
        if (is_array($selected_images)) {
            foreach ($selected_images as &$image) {
                if (isset($image['url'])) {
                    $image['url'] = str_replace('http://', 'https://', $image['url']);
                }
            }
        }

        $donation_data = array(
            'id' => $donation_id,
            'title' => $donation->post_title,
            'date' => get_the_date('F j, Y', $donation),
            'donor_name' => get_post_meta($donation_id, '_first_name', true) . ' ' . get_post_meta($donation_id, '_last_name', true),
            'donor_email' => get_post_meta($donation_id, '_email', true),
            'donor_phone' => get_post_meta($donation_id, '_phone', true),
            'donor_message' => get_post_meta($donation_id, '_message', true),
            'verification_file' => array(
                'url' => $verification_file_url,
                'type' => wp_check_filetype($verification_file)['type']
            ),
            'selected_images' => $selected_images,
            'status' => get_post_meta($donation_id, '_status', true) ?: 'pending'
        );

        $this->debug->log('Verification details retrieved', $donation_data);
        wp_send_json_success($donation_data);
    }

    /**
     * AJAX handler for updating donation status
     */
    public function ajax_update_status() {
        check_ajax_referer('beautiful_rescues_review_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $donation_id = intval($_POST['donation_id']);
        $status = sanitize_text_field($_POST['status']);

        if (!in_array($status, array('verified', 'rejected'))) {
            wp_send_json_error('Invalid status');
        }

        $this->debug->log('Updating donation status', array(
            'donation_id' => $donation_id,
            'status' => $status
        ));

        // Update status
        update_post_meta($donation_id, '_status', $status);

        // Get donation details for email
        $donor_name = get_post_meta($donation_id, '_first_name', true) . ' ' . get_post_meta($donation_id, '_last_name', true);
        $donor_email = get_post_meta($donation_id, '_email', true);
        $selected_images = get_post_meta($donation_id, '_selected_images', true);

        // Send email notification
        $this->send_status_notification($donor_email, $donor_name, $status, $selected_images);

        wp_send_json_success();
    }

    /**
     * AJAX handler for updating donation status from single post template
     */
    public function ajax_update_donation_status() {
        check_ajax_referer('beautiful_rescues_review_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $donation_id = intval($_POST['donation_id']);
        $status = sanitize_text_field($_POST['status']);

        if (!in_array($status, array('pending', 'verified', 'rejected'))) {
            wp_send_json_error('Invalid status');
            return;
        }

        // Update the donation status term
        wp_set_object_terms($donation_id, $status, 'donation_status');

        // Update the meta field for consistency
        update_post_meta($donation_id, '_status', $status);

        // Get donor details for notification
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($donation_id, '_first_name', true),
            get_post_meta($donation_id, '_last_name', true)
        );
        $donor_email = get_post_meta($donation_id, '_email', true);

        // Send email notification
        $this->send_status_notification($donor_email, $donor_name, $status);

        wp_send_json_success(array(
            'message' => __('Donation status updated successfully', 'beautiful-rescues')
        ));
    }

    /**
     * Send status notification email
     */
    private function send_status_notification($email, $name, $status, $selected_images = array()) {
        $this->debug->log('Preparing status notification email', array(
            'email' => $email,
            'name' => $name,
            'status' => $status,
            'selected_images' => $selected_images
        ));

        $subject = sprintf(
            'Your Donation Has Been %s',
            ucfirst($status)
        );

        $message = sprintf(
            "Dear %s,\n\n" .
            "Your donation has been %s. Thank you for your support!\n\n",
            $name,
            $status
        );

        if ($status === 'verified' && !empty($selected_images)) {
            $message .= "Your selected images are now available for download:\n\n";
            
            foreach ($selected_images as $image) {
                if (isset($image['url'])) {
                    $message .= sprintf(
                        "- %s: %s\n",
                        $image['filename'] ?? basename($image['url']),
                        $image['url']
                    );
                }
            }
        }

        $message .= "\nBest regards,\nBeautiful Rescues Team";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $sent = wp_mail($email, $subject, $message, $headers);

        $this->debug->log('Status notification email sent', array(
            'email' => $email,
            'name' => $name,
            'status' => $status,
            'sent' => $sent
        ));
    }
} 