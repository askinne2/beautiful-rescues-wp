<?php
/**
 * Donation Verification Class
 */
class BR_Donation_Verification {
    private static $instance = null;
    private $debug;

    private function __construct() {
        $this->debug = BR_Debug::get_instance();
        $this->init();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        // Register AJAX handlers
        add_action('wp_ajax_submit_donation_verification', array($this, 'handle_verification_submission'));
        add_action('wp_ajax_nopriv_submit_donation_verification', array($this, 'handle_verification_submission'));

        // Register shortcode
        add_shortcode('beautiful_rescues_verification_form', array($this, 'render_verification_form'));

        // Register form plugin support
        add_action('wpcf7_init', array($this, 'register_cf7_support'));
        add_action('wpforms_loaded', array($this, 'register_wpforms_support'));
        add_action('gform_loaded', array($this, 'register_gravityforms_support'));

        $this->debug->log('BR_Donation_Verification class initialized', null, 'info');
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
            <div class="required-field-note">
                <p><span class="required-mark">*</span> <?php _e('Required fields', 'beautiful-rescues'); ?></p>
            </div>
            
            <div class="form-group">
                <label for="_donor_first_name" data-required=" *"><?php _e('First Name', 'beautiful-rescues'); ?></label>
                <input type="text" id="_donor_first_name" name="_donor_first_name" required>
            </div>
            
            <div class="form-group">
                <label for="_donor_last_name" data-required=" *"><?php _e('Last Name', 'beautiful-rescues'); ?></label>
                <input type="text" id="_donor_last_name" name="_donor_last_name" required>
            </div>
            
            <div class="form-group">
                <label for="_donor_email" data-required=" *"><?php _e('Email Address', 'beautiful-rescues'); ?></label>
                <input type="email" id="_donor_email" name="_donor_email" required>
            </div>
            
            <div class="form-group">
                <label for="_donor_phone" data-required=" *"><?php _e('Phone Number', 'beautiful-rescues'); ?></label>
                <input type="tel" id="_donor_phone" name="_donor_phone" required>
            </div>
            
            <?php if ($atts['show_image_upload']) : ?>
            <div class="form-group">
                <label for="_selected_images" data-required=" *"><?php _e('Select Images', 'beautiful-rescues'); ?></label>
                <div id="image-preview" class="image-preview"></div>
                <input type="file" id="_selected_images" name="_selected_images" accept="image/*,application/pdf" multiple required>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="_verification_file" data-required=" *"><?php _e('Verification File (Image or PDF)', 'beautiful-rescues'); ?></label>
                <input type="file" id="_verification_file" name="_verification_file" accept="image/*,.pdf" required>
                <p class="help-text"><?php _e('Upload an Image (JPG or PNG) or PDF of your verification', 'beautiful-rescues'); ?></p>
            </div>

            <div class="form-group">
                <label for="_donor_message"><?php _e('Message (Optional)', 'beautiful-rescues'); ?></label>
                <textarea id="_donor_message" name="_donor_message" rows="4"></textarea>
            </div>
            
            <?php wp_nonce_field('beautiful_rescues_verification_nonce', 'verification_nonce'); ?>
            <input type="hidden" name="action" value="submit_donation_verification">
            <input type="hidden" name="source" value="<?php echo esc_attr($atts['source']); ?>">
            <input type="hidden" name="beautiful_rescues" value="1">
            
            <div class="form-group">
                <button type="submit" class="submit-button"><?php echo esc_html($atts['submit_button_text']); ?></button>
            </div>
            
            <div class="form-messages" id="form-messages"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle verification submission
     */
    public function handle_verification_submission() {
        $this->debug->log('Starting donation verification submission', null, 'info');

        // Verify nonce
        if (!isset($_POST['verification_nonce'])) {
            $this->debug->log('Security check failed: Nonce not found', null, 'error');
            wp_send_json_error(array(
                'message' => 'Security check failed. Please try again.'
            ));
            return;
        }

        if (!wp_verify_nonce($_POST['verification_nonce'], 'beautiful_rescues_verification_nonce')) {
            $this->debug->log('Security check failed: Invalid nonce', null, 'error');
            wp_send_json_error(array(
                'message' => 'Security check failed. Please try again.'
            ));
            return;
        }

        // Validate required fields
        $required_fields = array('_donor_first_name', '_donor_last_name', '_donor_email', '_donor_phone', '_verification_file');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field]) && empty($_FILES[$field])) {
                $this->debug->log("Validation failed: Missing required field: $field", null, 'error');
                wp_send_json_error(array(
                    'message' => 'Please fill in all required fields.'
                ));
                return;
            }
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $verification_dir = $upload_dir['basedir'] . '/verifications';
        
        if (!file_exists($verification_dir)) {
            if (!wp_mkdir_p($verification_dir)) {
                $this->debug->log('Failed to create verification directory', array('path' => $verification_dir), 'error');
                wp_send_json_error(array(
                    'message' => 'Failed to create upload directory.'
                ));
                return;
            }
        }

        // Move uploaded file
        $file = $_FILES['_verification_file'];
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid('verification-', true) . '.' . $file_ext;
        $destination = $verification_dir . '/' . $unique_filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->debug->log('Failed to move uploaded file', array('destination' => $destination), 'error');
            wp_send_json_error(array(
                'message' => 'Failed to save uploaded file.'
            ));
            return;
        }

        // Generate unique post title
        $post_title = sprintf(
            'Verification - %s %s - %s',
            sanitize_text_field($_POST['_donor_first_name']),
            sanitize_text_field($_POST['_donor_last_name']),
            current_time('Y-m-d H:i:s')
        );

        // Create verification post
        $post_data = array(
            'post_title' => $post_title,
            'post_type' => 'verification',
            'post_status' => 'publish'
        );

        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            $this->debug->log('Failed to create verification post', array('error' => $post_id->get_error_message()), 'error');
            wp_send_json_error(array(
                'message' => 'Failed to create verification record.'
            ));
            return;
        }

        // Generate and store a unique access token
        $access_token = wp_generate_password(32, false);
        update_post_meta($post_id, '_access_token', $access_token);

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
            }
        }

        // Store file path
        update_post_meta($post_id, '_verification_file', $unique_filename);
        update_post_meta($post_id, '_verification_file_path', $destination);

        // Store selected images
        if (!empty($_POST['selected_images'])) {
            $selected_images = json_decode(stripslashes($_POST['selected_images']), true);
            if (is_array($selected_images)) {
                update_post_meta($post_id, '_selected_images', $selected_images);
            }
        }

        // Set initial status and submission date
        update_post_meta($post_id, '_status', 'pending');
        update_post_meta($post_id, '_submission_date', current_time('mysql'));

        // Send email notifications
        $this->send_admin_notification($post_id);
        $this->send_donor_confirmation($post_id);

        $this->debug->log('Verification submission successful', array('post_id' => $post_id), 'info');

        // Return success response
        wp_send_json_success(array(
            'message' => 'Your verification has been submitted successfully.',
            'verification_id' => $post_id,
            'redirect_url' => isset($_POST['source']) && $_POST['source'] === 'checkout' 
                    ? get_permalink($post_id) . '?token=' . $access_token
                : null
        ));
    }

    /**
     * Process form submission
     */
    private function process_form_submission($fields, $files, $form_type = 'default') {
        error_log('========== START PROCESS FORM SUBMISSION ==========');
        error_log('Fields: ' . print_r($fields, true));
        error_log('Files: ' . print_r($files, true));
        error_log('Form type: ' . $form_type);

        // Create verification post
        $post_data = array(
            'post_title' => sprintf(
                'Verification - %s %s - %s',
                sanitize_text_field($fields['first_name']),
                sanitize_text_field($fields['last_name']),
                current_time('Y-m-d H:i:s')
            ),
            'post_type' => 'verification',
            'post_status' => 'publish'
        );

        $donation_id = wp_insert_post($post_data);
        if (is_wp_error($donation_id)) {
            error_log('Failed to create verification post: ' . $donation_id->get_error_message());
            return false;
        }
        error_log('Created verification post with ID: ' . $donation_id);

        // Handle file upload
        if (!empty($files['verification_file'])) {
            $file = $files['verification_file'];
            $upload_dir = wp_upload_dir();
            $verification_dir = $upload_dir['basedir'] . '/verifications';
            
            if (!file_exists($verification_dir)) {
                wp_mkdir_p($verification_dir);
            }

            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('verification-', true) . '.' . $file_ext;
            $destination = $verification_dir . '/' . $unique_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                update_post_meta($donation_id, '_verification_file', $unique_filename);
                update_post_meta($donation_id, '_verification_file_path', $destination);
                error_log('File uploaded successfully: ' . $destination);
            } else {
                error_log('Failed to move uploaded file to: ' . $destination);
            }
        }

        // Store verification data
        update_post_meta($donation_id, '_first_name', sanitize_text_field($fields['first_name']));
        update_post_meta($donation_id, '_last_name', sanitize_text_field($fields['last_name']));
        update_post_meta($donation_id, '_email', sanitize_email($fields['email']));
        update_post_meta($donation_id, '_phone', sanitize_text_field($fields['phone']));
        update_post_meta($donation_id, '_message', sanitize_textarea_field($fields['message']));
        update_post_meta($donation_id, '_status', 'pending');
        update_post_meta($donation_id, '_form_type', $form_type);

        // Handle selected images if present
        if (!empty($fields['selected_images'])) {
            $selected_images = json_decode(stripslashes($fields['selected_images']), true);
            if (is_array($selected_images)) {
                update_post_meta($donation_id, '_selected_images', $selected_images);
                error_log('Stored selected images: ' . print_r($selected_images, true));
            }
        }

        error_log('Stored verification data');
        error_log('========== END PROCESS FORM SUBMISSION ==========');
        return $donation_id;
    }

    /**
     * Send admin notification
     */
    private function send_admin_notification($post_id) {
        $this->debug->log('========== START ADMIN NOTIFICATION ==========');
        $this->debug->log('Post ID: ' . $post_id);
        
        // Get verification email from settings, fallback to admin email
        $options = get_option('beautiful_rescues_options', array());
        $admin_email = $options['verification_email'] ?? get_option('admin_email');
        $this->debug->log('Admin email: ' . $admin_email);
        
        $donor_email = get_post_meta($post_id, '_email', true);
        $this->debug->log('Donor email: ' . $donor_email);
        
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($post_id, '_first_name', true),
            get_post_meta($post_id, '_last_name', true)
        );
        $this->debug->log('Donor name: ' . $donor_name);

        // Get verification status
        $status = get_post_meta($post_id, '_status', true);
        $this->debug->log('Status: ' . $status);

        // Get access token for admin view
        $access_token = get_post_meta($post_id, '_access_token', true);
        $verification_url = get_permalink($post_id) . '?token=' . $access_token;

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
            $verification_url,
            home_url('/review-donations/')
        );

        $this->debug->log('Admin email subject: ' . $admin_subject);
        $this->debug->log('Admin email message: ' . $admin_message);

        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_message);
        $this->debug->log('Admin email sent: ' . ($admin_sent ? 'Yes' : 'No'));
        $this->debug->log('========== END ADMIN NOTIFICATION ==========');
    }

    /**
     * Send donor confirmation
     */
    private function send_donor_confirmation($post_id) {
        error_log('========== START DONOR CONFIRMATION ==========');
        error_log('Post ID: ' . $post_id);
        
        $donor_email = get_post_meta($post_id, '_email', true);
        error_log('Donor email: ' . $donor_email);
        
        $donor_name = sprintf(
            '%s %s',
            get_post_meta($post_id, '_first_name', true),
            get_post_meta($post_id, '_last_name', true)
        );
        error_log('Donor name: ' . $donor_name);

        // Get verification status
        $status = get_post_meta($post_id, '_status', true);
        error_log('Status: ' . $status);
        
        // Get access token for verification link
        $access_token = get_post_meta($post_id, '_access_token', true);
        $verification_url = get_permalink($post_id) . '?token=' . $access_token;

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
            "You can view your verification details at any time by visiting this link:\n" .
            "%s\n\n" .
            "We will contact you once we've reviewed your verification.\n\n" .
            "Best regards,\n" .
            "Beautiful Rescues Team",
            $donor_name,
            $donor_name,
            $donor_email,
            get_post_meta($post_id, '_phone', true),
            get_post_meta($post_id, '_message', true),
            $status,
            $verification_url
        );

        error_log('Donor email subject: ' . $donor_subject);
        error_log('Donor email message: ' . $donor_message);

        $donor_sent = wp_mail($donor_email, $donor_subject, $donor_message);
        error_log('Donor email sent: ' . ($donor_sent ? 'Yes' : 'No'));
        error_log('========== END DONOR CONFIRMATION ==========');
    }
} 