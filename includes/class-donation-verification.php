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
    public function render_verification_form($atts = array()) {
        // Parse attributes
        $atts = wp_parse_args($atts, array(
            'source' => 'default',
            'show_image_upload' => true
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
        <form id="donation-verification-form" class="beautiful-rescues-verification-form" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="first_name"><?php _e('First Name', 'beautiful-rescues'); ?> *</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            
            <div class="form-group">
                <label for="last_name"><?php _e('Last Name', 'beautiful-rescues'); ?> *</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            
            <div class="form-group">
                <label for="email"><?php _e('Email Address', 'beautiful-rescues'); ?> *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="phone"><?php _e('Phone Number', 'beautiful-rescues'); ?> *</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            
            <?php if ($atts['show_image_upload']) : ?>
            <div class="form-group">
                <label for="selected_images"><?php _e('Select Images', 'beautiful-rescues'); ?> *</label>
                <div id="image-preview" class="image-preview"></div>
                <input type="file" id="selected_images" name="selected_images" accept="image/*,application/pdf" multiple required>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="donation_verification"><?php _e('Donation Verification (Image or PDF)', 'beautiful-rescues'); ?> *</label>
                <input type="file" id="donation_verification" name="donation_verification" accept="image/*,.pdf" required>
                <p class="help-text"><?php _e('Upload a screenshot or PDF of your donation receipt', 'beautiful-rescues'); ?></p>
            </div>

            <div class="form-group">
                <label for="message"><?php _e('Message (Optional)', 'beautiful-rescues'); ?></label>
                <textarea id="message" name="message" rows="4"></textarea>
            </div>
            
            <input type="hidden" name="action" value="submit_donation_verification">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('beautiful_rescues_verification_nonce'); ?>">
            <input type="hidden" name="source" value="<?php echo esc_attr($atts['source']); ?>">
            
            <div class="form-group">
                <button type="submit" class="submit-button"><?php 
                    echo $atts['source'] === 'checkout' 
                        ? __('Complete Checkout', 'beautiful-rescues')
                        : __('Submit Verification', 'beautiful-rescues'); 
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
        // Check nonce with correct nonce name
        check_ajax_referer('beautiful_rescues_verification_nonce', 'nonce');

        $response = array(
            'success' => false,
            'message' => ''
        );

        try {
            // Debug log the incoming data
            error_log('Donation verification submission received: ' . print_r($_POST, true));
            error_log('Files received: ' . print_r($_FILES, true));

            // Validate required fields with correct field names
            $required_fields = array('first_name', 'last_name', 'email', 'phone');
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

            // Handle file upload with correct field name
            if (empty($_FILES['donation_verification'])) {
                throw new Exception('Please upload your donation verification');
            }

            $file = $_FILES['donation_verification'];
            $options = get_option('beautiful_rescues_options', array());
            $max_file_size = isset($options['max_file_size']) ? (int)$options['max_file_size'] : 5; // Default to 5MB
            $max_file_size_bytes = $max_file_size * 1024 * 1024; // Convert MB to bytes

            // Validate file size
            if ($file['size'] > $max_file_size_bytes) {
                throw new Exception(sprintf('File size must be less than %dMB', $max_file_size));
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

            // Set initial donation status
            wp_set_object_terms($donation_id, 'pending', 'donation_status');

            // Store verification data with correct field names and underscore prefix
            $meta_fields = array(
                '_donor_first_name' => sanitize_text_field($_POST['first_name']),
                '_donor_last_name' => sanitize_text_field($_POST['last_name']),
                '_donor_email' => sanitize_email($_POST['email']),
                '_donor_phone' => sanitize_text_field($_POST['phone']),
                '_donor_message' => sanitize_textarea_field($_POST['message'] ?? ''),
                '_selected_images' => $selected_images,
                '_verification_file' => $unique_filename,
                '_verification_file_url' => $verification_url,
                '_verification_file_path' => $file_path,
                '_verification_status' => 'pending',
                '_submission_date' => $timestamp,
                '_form_source' => sanitize_text_field($_POST['source'] ?? 'checkout')
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
        $donor_email = get_post_meta($donation_id, '_donor_email', true);
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($donation_id, '_donor_first_name', true),
            get_post_meta($donation_id, '_donor_last_name', true)
        );

        // Get donation status
        $status_terms = wp_get_object_terms($donation_id, 'donation_status');
        $status = !empty($status_terms) ? $status_terms[0]->name : 'Pending';

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
            get_post_meta($donation_id, '_donor_phone', true),
            get_post_meta($donation_id, '_donor_message', true),
            $status,
            admin_url('post.php?post=' . $donation_id . '&action=edit'),
            home_url('/review-donations/')
        );

        wp_mail($admin_email, $admin_subject, $admin_message);
    }

    /**
     * Send donor confirmation
     */
    private function send_donor_confirmation($donation_id) {
        $donor_email = get_post_meta($donation_id, '_donor_email', true);
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($donation_id, '_donor_first_name', true),
            get_post_meta($donation_id, '_donor_last_name', true)
        );

        // Get donation status
        $status_terms = wp_get_object_terms($donation_id, 'donation_status');
        $status = !empty($status_terms) ? $status_terms[0]->name : 'Pending';

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
            get_post_meta($donation_id, '_donor_phone', true),
            get_post_meta($donation_id, '_donor_message', true),
            $status
        );

        wp_mail($donor_email, $donor_subject, $donor_message);
    }
} 