<?php
/**
 * Donation Verification Class
 */
class BR_Donation_Verification {
    public function __construct() {
        error_log('BR_Donation_Verification class instantiated');
        
        // Add AJAX handlers
        add_action('wp_ajax_submit_donation_verification', array($this, 'handle_verification_submission'));
        add_action('wp_ajax_nopriv_submit_donation_verification', array($this, 'handle_verification_submission'));
        error_log('AJAX handlers registered for submit_donation_verification');
        
        // Add shortcode for verification form
        add_shortcode('beautiful_rescues_verification_form', array($this, 'render_verification_form'));
        error_log('Shortcode registered for beautiful_rescues_verification_form');
        
        // Add form plugin support
        add_action('wpforms_process_complete', array($this, 'handle_wpforms_submission'), 10, 4);
        add_action('forminator_form_after_save_entry', array($this, 'handle_forminator_submission'), 10, 2);
        error_log('Form plugin support actions registered');
    }

    /**
     * Render verification form
     */
    public function render_verification_form($atts = array()) {
        // Parse attributes
        $atts = wp_parse_args($atts, array(
            'source' => 'default',
            'show_image_upload' => true,
            'form_id' => 'donation-verification-form',
            'submit_button_text' => __('Submit Verification', 'beautiful-rescues')
        ));

        wp_enqueue_style('beautiful-rescues-verification', BR_PLUGIN_URL . 'public/css/verification.css');
        wp_enqueue_script('beautiful-rescues-verification', BR_PLUGIN_URL . 'public/js/verification.js', array('jquery'), BR_VERSION, true);
        
        // Get options with default values
        $options = get_option('beautiful_rescues_options', array());
        $max_file_size = isset($options['max_file_size']) ? (int)$options['max_file_size'] : 5; // Default to 5MB
        
        wp_localize_script('beautiful-rescues-verification', 'beautifulRescuesVerification', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_verification_nonce'),
            'maxFileSize' => $max_file_size * 1024 * 1024, // Convert MB to bytes
            'source' => $atts['source']
        ));

        ob_start();
        ?>
        <form id="<?php echo esc_attr($atts['form_id']); ?>" class="beautiful-rescues-verification-form" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="_donor_first_name"><?php _e('First Name', 'beautiful-rescues'); ?> *</label>
                <input type="text" id="_donor_first_name" name="_donor_first_name" required>
            </div>
            
            <div class="form-group">
                <label for="_donor_last_name"><?php _e('Last Name', 'beautiful-rescues'); ?> *</label>
                <input type="text" id="_donor_last_name" name="_donor_last_name" required>
            </div>
            
            <div class="form-group">
                <label for="_donor_email"><?php _e('Email Address', 'beautiful-rescues'); ?> *</label>
                <input type="email" id="_donor_email" name="_donor_email" required>
            </div>
            
            <div class="form-group">
                <label for="_donor_phone"><?php _e('Phone Number', 'beautiful-rescues'); ?> *</label>
                <input type="tel" id="_donor_phone" name="_donor_phone" required>
            </div>
            
            <?php if ($atts['show_image_upload']) : ?>
            <div class="form-group">
                <label for="_selected_images"><?php _e('Select Images', 'beautiful-rescues'); ?> *</label>
                <div id="image-preview" class="image-preview"></div>
                <input type="file" id="_selected_images" name="_selected_images" accept="image/*,application/pdf" multiple required>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="_verification_file"><?php _e('Verification File (Image or PDF)', 'beautiful-rescues'); ?> *</label>
                <input type="file" id="_verification_file" name="_verification_file" accept="image/*,.pdf" required>
                <p class="help-text"><?php _e('Upload a screenshot or PDF of your verification', 'beautiful-rescues'); ?></p>
            </div>

            <div class="form-group">
                <label for="_donor_message"><?php _e('Message (Optional)', 'beautiful-rescues'); ?></label>
                <textarea id="_donor_message" name="_donor_message" rows="4"></textarea>
            </div>
            
            <?php wp_nonce_field('beautiful_rescues_verification_nonce', 'verification_nonce'); ?>
            <input type="hidden" name="action" value="submit_donation_verification">
            <input type="hidden" name="source" value="<?php echo esc_attr($atts['source']); ?>">
            
            <div class="form-group">
                <button type="submit" class="submit-button"><?php 
                    echo esc_html($atts['submit_button_text']); 
                ?></button>
            </div>
            
            <div id="form-messages" class="form-messages"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle verification submission
     */
    public function handle_verification_submission() {
        error_log('========== START DONATION VERIFICATION SUBMISSION ==========');
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Request URI: ' . $_SERVER['REQUEST_URI']);
        error_log('POST data received: ' . print_r($_POST, true));
        error_log('FILES data received: ' . print_r($_FILES, true));
        error_log('Raw POST data: ' . file_get_contents('php://input'));

        // Verify nonce
        if (!isset($_POST['verification_nonce'])) {
            error_log('Nonce not found in POST data');
            wp_send_json_error(array(
                'message' => 'Security check failed. Please try again.'
            ));
            return;
        }

        if (!wp_verify_nonce($_POST['verification_nonce'], 'beautiful_rescues_verification_nonce')) {
            error_log('Nonce verification failed. Received: ' . $_POST['verification_nonce']);
            wp_send_json_error(array(
                'message' => 'Security check failed. Please try again.'
            ));
            return;
        }
        error_log('Nonce verification passed');

        // Validate required fields
        $required_fields = array('_donor_first_name', '_donor_last_name', '_donor_email', '_donor_phone', '_verification_file');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field]) && empty($_FILES[$field])) {
                error_log("Required field missing: $field");
                wp_send_json_error(array(
                    'message' => 'Please fill in all required fields.'
                ));
                return;
            }
        }
        error_log('Required fields validation passed');

        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $verification_dir = $upload_dir['basedir'] . '/verifications';
        
        if (!file_exists($verification_dir)) {
            if (!wp_mkdir_p($verification_dir)) {
                error_log('Failed to create verification directory: ' . $verification_dir);
                wp_send_json_error(array(
                    'message' => 'Failed to create upload directory.'
                ));
                return;
            }
        }
        error_log('Upload directory check passed');

        // Move uploaded file
        $file = $_FILES['_verification_file'];
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('verification-', true) . '.' . $file_ext;
        $destination = $verification_dir . '/' . $unique_filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            error_log('Failed to move uploaded file to: ' . $destination);
            wp_send_json_error(array(
                'message' => 'Failed to save uploaded file.'
            ));
            return;
        }
        error_log('File upload successful: ' . $destination);

        // Generate unique post title
        $post_title = sprintf(
            'Verification - %s %s - %s',
            sanitize_text_field($_POST['_donor_first_name']),
            sanitize_text_field($_POST['_donor_last_name']),
            current_time('Y-m-d H:i:s')
        );
        error_log('Generated post title: ' . $post_title);

        // Create verification post
        $post_data = array(
            'post_title' => $post_title,
            'post_type' => 'verification',
            'post_status' => 'pending'
        );

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            error_log('Failed to create verification post: ' . $post_id->get_error_message());
            wp_send_json_error(array(
                'message' => 'Failed to create verification record.'
            ));
            return;
        }
        error_log('Created verification post with ID: ' . $post_id);

        // Store verification data
        $meta_fields = array(
            '_first_name' => '_donor_first_name',
            '_last_name' => '_donor_last_name',
            '_email' => '_donor_email',
            '_phone' => '_donor_phone',
            '_message' => '_donor_message'
        );

        foreach ($meta_fields as $new_field => $old_field) {
            if (isset($_POST[$old_field])) {
                update_post_meta($post_id, $new_field, sanitize_text_field($_POST[$old_field]));
                error_log("Updated post meta: $new_field");
            }
        }

        // Store file path
        update_post_meta($post_id, '_verification_file', $unique_filename);
        update_post_meta($post_id, '_verification_file_path', $destination);
        error_log('Stored verification file path');

        // Store selected images
        if (!empty($_POST['selected_images'])) {
            error_log('Processing selected images: ' . $_POST['selected_images']);
            $selected_images = json_decode(stripslashes($_POST['selected_images']), true);
            if (is_array($selected_images)) {
                update_post_meta($post_id, '_selected_images', $selected_images);
                error_log('Stored selected images: ' . print_r($selected_images, true));
            } else {
                error_log('Failed to decode selected images JSON');
            }
        } else {
            error_log('No selected images found in POST data');
        }

        // Set initial status
        update_post_meta($post_id, '_status', 'pending');
        error_log('Set initial verification status to pending');

        // Set submission date
        update_post_meta($post_id, '_submission_date', current_time('mysql'));
        error_log('Set submission date');

        // Send notifications
        do_action('beautiful_rescues_verification_submitted', $post_id);
        error_log('Triggered verification submitted action');

        error_log('Verification submission successful. Post ID: ' . $post_id);
        error_log('========== END DONATION VERIFICATION SUBMISSION ==========');

        // Return success response
        wp_send_json_success(array(
            'message' => 'Your verification has been submitted successfully.',
            'redirect_url' => isset($_POST['source']) && $_POST['source'] === 'checkout' 
                ? home_url('/thank-you/') 
                : null
        ));
    }

    /**
     * Process form submission
     */
    private function process_form_submission($fields, $form_type) {
        // Extract selected images from the form submission
        $selected_images = array();
        if (isset($fields['selected_cloudinary_images'])) {
            $selected_images = json_decode(stripslashes($fields['selected_cloudinary_images']), true);
        }

        if (empty($selected_images)) {
            return;
        }

        // Create donation post
        $donation_data = array(
            'post_title' => sprintf(
                'Donation from %s %s',
                sanitize_text_field($fields['first_name']),
                sanitize_text_field($fields['last_name'])
            ),
            'post_type' => 'verification',
            'post_status' => 'publish'
        );

        $donation_id = wp_insert_post($donation_data);

        if (is_wp_error($donation_id)) {
            return;
        }

        // Store verification data
        update_post_meta($donation_id, '_first_name', sanitize_text_field($fields['first_name']));
        update_post_meta($donation_id, '_last_name', sanitize_text_field($fields['last_name']));
        update_post_meta($donation_id, '_email', sanitize_email($fields['email']));
        update_post_meta($donation_id, '_phone', sanitize_text_field($fields['phone']));
        update_post_meta($donation_id, '_selected_images', $selected_images);
        update_post_meta($donation_id, '_status', 'pending');
        update_post_meta($donation_id, '_form_type', $form_type);

        // Send email notification
        $this->send_admin_notification($donation_id);
    }

    /**
     * Send admin notification
     */
    private function send_admin_notification($post_id) {
        $admin_email = get_option('admin_email');
        $donor_email = get_post_meta($post_id, '_email', true);
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($post_id, '_first_name', true),
            get_post_meta($post_id, '_last_name', true)
        );

        // Get verification status
        $status = get_post_meta($post_id, '_status', true);

        // Admin notification
        $admin_subject = sprintf('New Donation Verification from %s', $donor_name);
        $admin_message = sprintf(
            "A new donation verification has been submitted:\n\n" .
            "Donor: %s\n" .
            "Email: %s\n" .
            "Phone: %s\n" .
            "Message: %s\n" .
            "Status: %s\n\n" .
            "View verification: %s\n\n" .
            "Review verification: %s",
            $donor_name,
            $donor_email,
            get_post_meta($post_id, '_phone', true),
            get_post_meta($post_id, '_message', true),
            $status,
            admin_url('post.php?post=' . $post_id . '&action=edit'),
            home_url('/review-donations/')
        );

        wp_mail($admin_email, $admin_subject, $admin_message);
    }

    /**
     * Send donor confirmation
     */
    private function send_donor_confirmation($post_id) {
        $donor_email = get_post_meta($post_id, '_email', true);
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($post_id, '_first_name', true),
            get_post_meta($post_id, '_last_name', true)
        );

        // Get verification status
        $status = get_post_meta($post_id, '_status', true);

        $donor_subject = 'Thank you for your donation verification';
        $donor_message = sprintf(
            "Dear %s,\n\n" .
            "Thank you for submitting your donation verification. We have received your submission and will review it shortly.\n\n" .
            "Here's a summary of your submission:\n" .
            "- Name: %s\n" .
            "- Email: %s\n" .
            "- Phone: %s\n" .
            "- Message: %s\n" .
            "- Status: %s\n\n" .
            "We will contact you once we've reviewed your verification.\n\n" .
            "Best regards,\n" .
            "Beautiful Rescues Team",
            $donor_name,
            $donor_name,
            $donor_email,
            get_post_meta($post_id, '_phone', true),
            get_post_meta($post_id, '_message', true),
            $status
        );

        wp_mail($donor_email, $donor_subject, $donor_message);
    }
} 