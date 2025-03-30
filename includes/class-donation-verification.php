<?php
/**
 * Donation Verification Class
 */
class BR_Donation_Verification {
    public function __construct() {
        // Add AJAX handlers
        add_action('wp_ajax_submit_donation_verification', array($this, 'handle_verification_submission'));
        add_action('wp_ajax_nopriv_submit_donation_verification', array($this, 'handle_verification_submission'));
        
        // Add shortcode for verification form
        add_shortcode('beautiful_rescues_verification_form', array($this, 'render_verification_form'));
        
        // Add form plugin support
        add_action('wpforms_process_complete', array($this, 'handle_wpforms_submission'), 10, 4);
        add_action('forminator_form_after_save_entry', array($this, 'handle_forminator_submission'), 10, 2);
    }

    /**
     * Render verification form
     */
    public function render_verification_form($atts) {
        wp_enqueue_style('beautiful-rescues-verification', BR_PLUGIN_URL . 'public/css/verification.css');
        wp_enqueue_script('beautiful-rescues-verification', BR_PLUGIN_URL . 'public/js/verification.js', array('jquery'), BR_VERSION, true);
        
        wp_localize_script('beautiful-rescues-verification', 'beautifulRescuesVerification', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_verification_nonce'),
            'maxFileSize' => get_option('beautiful_rescues_options')['max_file_size'] * 1024 * 1024
        ));

        ob_start();
        ?>
        <form id="donation-verification-form" class="beautiful-rescues-verification-form">
            <div class="form-group">
                <label for="first_name">First Name *</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone">
            </div>
            
            <div class="form-group">
                <label for="selected_images">Select Images *</label>
                <div id="image-preview" class="image-preview"></div>
                <input type="file" id="selected_images" name="selected_images" accept="image/*,application/pdf" multiple required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="submit-button">Submit Verification</button>
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
        check_ajax_referer('beautiful_rescues_gallery_nonce', 'nonce');

        $response = array(
            'success' => false,
            'message' => ''
        );

        try {
            // Debug log the incoming data
            error_log('Donation verification submission received: ' . print_r($_POST, true));
            error_log('Files received: ' . print_r($_FILES, true));

            // Validate required fields
            $required_fields = array('firstName', 'lastName', 'email', 'phone');
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            // Validate email
            if (!is_email($_POST['email'])) {
                throw new Exception('Invalid email address');
            }

            // Validate phone (basic validation)
            if (!preg_match('/^\+?[1-9]\d{1,14}$/', $_POST['phone'])) {
                throw new Exception('Invalid phone number format');
            }

            // Handle file upload
            if (empty($_FILES['donationVerification'])) {
                throw new Exception('Please upload your donation verification');
            }

            $file = $_FILES['donationVerification'];
            $max_file_size = (int) (get_option('beautiful_rescues_options')['max_file_size'] ?? 5) * 1024 * 1024; // Convert MB to bytes

            // Validate file size
            if ($file['size'] > $max_file_size) {
                throw new Exception(sprintf('File size must be less than %dMB', (int) (get_option('beautiful_rescues_options')['max_file_size'] ?? 5)));
            }

            // Validate file type
            $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Please upload an image or PDF');
            }

            // Create donation-verifications directory in uploads if it doesn't exist
            $upload_dir = wp_upload_dir();
            $donation_verifications_dir = $upload_dir['basedir'] . '/donation-verifications';
            if (!file_exists($donation_verifications_dir)) {
                wp_mkdir_p($donation_verifications_dir);
            }

            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = wp_generate_password(8, false) . '-' . time() . '.' . $file_extension;
            $file_path = $donation_verifications_dir . '/' . $unique_filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Failed to save verification file');
            }

            // Store the full URL for the verification file
            $verification_url = $upload_dir['baseurl'] . '/donation-verifications/' . $unique_filename;

            // Get selected images
            $selected_images = json_decode(stripslashes($_POST['selected_images']), true);
            error_log('Selected images from form: ' . print_r($selected_images, true));
            
            if (empty($selected_images)) {
                throw new Exception('No images selected');
            }

            // Generate unique post title with timestamp
            $timestamp = current_time('mysql');
            $post_title = sprintf(
                'Donation #%s - %s',
                wp_generate_password(8, false),
                date('Y-m-d H:i:s', strtotime($timestamp))
            );

            // Create donation post
            $donation_data = array(
                'post_title' => $post_title,
                'post_type' => 'donation',
                'post_status' => 'publish',
                'post_date' => $timestamp
            );

            $donation_id = wp_insert_post($donation_data);

            if (is_wp_error($donation_id)) {
                throw new Exception('Failed to create donation record');
            }

            // Store verification data
            $meta_fields = array(
                'donor_first_name' => sanitize_text_field($_POST['firstName']),
                'donor_last_name' => sanitize_text_field($_POST['lastName']),
                'donor_email' => sanitize_email($_POST['email']),
                'donor_phone' => sanitize_text_field($_POST['phone']),
                'donor_message' => sanitize_textarea_field($_POST['message'] ?? ''),
                'selected_images' => $selected_images,
                'verification_file' => $unique_filename,
                'verification_file_url' => $verification_url,
                'verification_file_path' => $file_path,
                'verification_status' => 'pending',
                'submission_date' => $timestamp
            );

            // Debug log the meta fields
            error_log('Storing meta fields: ' . print_r($meta_fields, true));

            foreach ($meta_fields as $key => $value) {
                $result = update_post_meta($donation_id, $key, $value);
                error_log("Updating meta field {$key}: " . ($result ? 'success' : 'failed'));
            }

            // Send email notifications
            $this->send_admin_notification($donation_id);
            $this->send_donor_confirmation($donation_id);

            $response['success'] = true;
            $response['message'] = 'Thank you for your donation! We will review your verification and get back to you soon.';
            $response['redirect'] = home_url('/confirmation/');

        } catch (Exception $e) {
            error_log('Donation verification error: ' . $e->getMessage());
            $response['message'] = $e->getMessage();
        }

        wp_send_json($response);
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
            'post_type' => 'donation',
            'post_status' => 'publish'
        );

        $donation_id = wp_insert_post($donation_data);

        if (is_wp_error($donation_id)) {
            return;
        }

        // Store verification data
        update_post_meta($donation_id, '_donor_first_name', sanitize_text_field($fields['first_name']));
        update_post_meta($donation_id, '_donor_last_name', sanitize_text_field($fields['last_name']));
        update_post_meta($donation_id, '_donor_email', sanitize_email($fields['email']));
        update_post_meta($donation_id, '_donor_phone', sanitize_text_field($fields['phone']));
        update_post_meta($donation_id, '_selected_images', $selected_images);
        update_post_meta($donation_id, '_verification_status', 'pending');
        update_post_meta($donation_id, '_form_type', $form_type);

        // Send email notification
        $this->send_admin_notification($donation_id);
    }

    /**
     * Send admin notification
     */
    private function send_admin_notification($donation_id) {
        $admin_email = get_option('admin_email');
        $donor_email = get_post_meta($donation_id, 'donor_email', true);
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($donation_id, 'donor_first_name', true),
            get_post_meta($donation_id, 'donor_last_name', true)
        );

        // Admin notification
        $admin_subject = sprintf('New Donation Verification from %s', $donor_name);
        $admin_message = sprintf(
            "A new donation verification has been submitted:\n\n" .
            "Donor: %s\n" .
            "Email: %s\n" .
            "Phone: %s\n" .
            "Message: %s\n" .
            "Status: Pending\n\n" .
            "View verification: %s\n\n" .
            "Review verification: %s",
            $donor_name,
            $donor_email,
            get_post_meta($donation_id, 'donor_phone', true),
            get_post_meta($donation_id, 'donor_message', true),
            admin_url('post.php?post=' . $donation_id . '&action=edit'),
            home_url('/review-donations/')
        );

        wp_mail($admin_email, $admin_subject, $admin_message);
    }

    /**
     * Send donor confirmation
     */
    private function send_donor_confirmation($donation_id) {
        $donor_email = get_post_meta($donation_id, 'donor_email', true);
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($donation_id, 'donor_first_name', true),
            get_post_meta($donation_id, 'donor_last_name', true)
        );

        $donor_subject = 'Thank you for your donation verification';
        $donor_message = sprintf(
            "Dear %s,\n\n" .
            "Thank you for submitting your donation verification. We have received your submission and will review it shortly.\n\n" .
            "Here's a summary of your submission:\n" .
            "- Name: %s\n" .
            "- Email: %s\n" .
            "- Phone: %s\n" .
            "- Message: %s\n\n" .
            "We will contact you once we've reviewed your verification.\n\n" .
            "Best regards,\n" .
            "Beautiful Rescues Team",
            $donor_name,
            $donor_name,
            $donor_email,
            get_post_meta($donation_id, 'donor_phone', true),
            get_post_meta($donation_id, 'donor_message', true)
        );

        wp_mail($donor_email, $donor_subject, $donor_message);
    }
} 