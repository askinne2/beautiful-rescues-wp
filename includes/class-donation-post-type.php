<?php
/**
 * Donation Post Type Class
 */
class BR_Donation_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    /**
     * Register the donation post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x('Donations', 'post type general name', 'beautiful-rescues'),
            'singular_name'      => _x('Donation', 'post type singular name', 'beautiful-rescues'),
            'menu_name'          => _x('Donations', 'admin menu', 'beautiful-rescues'),
            'add_new'            => _x('Add New', 'donation', 'beautiful-rescues'),
            'add_new_item'       => __('Add New Donation', 'beautiful-rescues'),
            'edit_item'          => __('Edit Donation', 'beautiful-rescues'),
            'new_item'           => __('New Donation', 'beautiful-rescues'),
            'view_item'          => __('View Donation', 'beautiful-rescues'),
            'search_items'       => __('Search Donations', 'beautiful-rescues'),
            'not_found'          => __('No donations found', 'beautiful-rescues'),
            'not_found_in_trash' => __('No donations found in Trash', 'beautiful-rescues'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'beautiful-rescues',
            'query_var'           => true,
            'rewrite'             => array('slug' => 'donation'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'menu_icon'           => 'dashicons-heart',
            'supports'            => array('title'),
            'show_in_rest'        => false,
        );

        register_post_type('donation', $args);

        // Register donation status taxonomy
        register_taxonomy('donation_status', 'donation', array(
            'label'        => __('Donation Status', 'beautiful-rescues'),
            'hierarchical' => true,
            'show_in_rest' => false,
            'rewrite'      => array('slug' => 'donation-status'),
        ));

        // Add default terms
        $this->add_default_status_terms();
    }

    /**
     * Add default donation status terms
     */
    private function add_default_status_terms() {
        $default_terms = array(
            'pending'    => __('Pending Verification', 'beautiful-rescues'),
            'verified'   => __('Verified', 'beautiful-rescues'),
            'rejected'   => __('Rejected', 'beautiful-rescues'),
        );

        foreach ($default_terms as $slug => $name) {
            if (!term_exists($slug, 'donation_status')) {
                wp_insert_term($name, 'donation_status', array('slug' => $slug));
            }
        }
    }

    /**
     * Add meta boxes for donation details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'donation_details',
            __('Donation Details', 'beautiful-rescues'),
            array($this, 'render_donation_details_meta_box'),
            'donation',
            'normal',
            'high'
        );
    }

    /**
     * Render donation details meta box
     */
    public function render_donation_details_meta_box($post) {
        // Get the verification file path
        $verification_file = get_post_meta($post->ID, 'verification_file', true);
        $verification_file_path = get_post_meta($post->ID, 'verification_file_path', true);
        
        // Get other meta fields
        $donor_first_name = get_post_meta($post->ID, 'donor_first_name', true);
        $donor_last_name = get_post_meta($post->ID, 'donor_last_name', true);
        $donor_email = get_post_meta($post->ID, 'donor_email', true);
        $donor_phone = get_post_meta($post->ID, 'donor_phone', true);
        $donor_message = get_post_meta($post->ID, 'donor_message', true);
        $selected_images = get_post_meta($post->ID, 'selected_images', true);
        $verification_status = get_post_meta($post->ID, 'verification_status', true);
        $submission_date = get_post_meta($post->ID, 'submission_date', true);
        
        // Add nonce for security
        wp_nonce_field('donation_details_meta_box', 'donation_details_meta_box_nonce');
        ?>
        <div class="donation-details-meta-box">
            <div class="donation-field">
                <label for="donor_first_name">First Name:</label>
                <input type="text" id="donor_first_name" name="donor_first_name" value="<?php echo esc_attr($donor_first_name); ?>" readonly>
            </div>
            
            <div class="donation-field">
                <label for="donor_last_name">Last Name:</label>
                <input type="text" id="donor_last_name" name="donor_last_name" value="<?php echo esc_attr($donor_last_name); ?>" readonly>
            </div>
            
            <div class="donation-field">
                <label for="donor_email">Email:</label>
                <input type="email" id="donor_email" name="donor_email" value="<?php echo esc_attr($donor_email); ?>" readonly>
            </div>
            
            <div class="donation-field">
                <label for="donor_phone">Phone:</label>
                <input type="tel" id="donor_phone" name="donor_phone" value="<?php echo esc_attr($donor_phone); ?>" readonly>
            </div>
            
            <div class="donation-field">
                <label for="donor_message">Message:</label>
                <textarea id="donor_message" name="donor_message" readonly><?php echo esc_textarea($donor_message); ?></textarea>
            </div>
            
            <div class="donation-field">
                <label>Verification File:</label>
                <?php if ($verification_file && $verification_file_path): ?>
                    <div class="verification-file-container">
                        <?php
                        $file_extension = strtolower(pathinfo($verification_file, PATHINFO_EXTENSION));
                        $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                        $file_url = wp_upload_dir()['baseurl'] . '/donation-verifications/' . $verification_file;
                        ?>
                        
                        <div class="file-preview">
                            <?php if ($is_image): ?>
                                <img src="<?php echo esc_url($file_url); ?>" alt="Verification file preview" class="file-preview-image">
                            <?php else: ?>
                                <div class="file-preview-pdf">
                                    <span class="dashicons dashicons-media-document"></span>
                                    <span class="file-type">PDF Document</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="file-info">
                            <p class="file-path"><?php echo esc_html($verification_file_path); ?></p>
                            <a href="<?php echo esc_url($file_url); ?>" 
                               class="button button-primary" 
                               target="_blank">
                                Download File
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No file uploaded</p>
                <?php endif; ?>
            </div>
            
            <div class="donation-field">
                <label for="verification_status">Verification Status:</label>
                <select id="verification_status" name="verification_status">
                    <option value="pending" <?php selected($verification_status, 'pending'); ?>>Pending</option>
                    <option value="verified" <?php selected($verification_status, 'verified'); ?>>Verified</option>
                    <option value="rejected" <?php selected($verification_status, 'rejected'); ?>>Rejected</option>
                </select>
            </div>
            
            <div class="donation-field">
                <label>Submission Date:</label>
                <input type="text" value="<?php echo esc_attr($submission_date); ?>" readonly>
            </div>
            
            <?php if (!empty($selected_images)): ?>
                <div class="donation-field">
                    <label>Selected Images:</label>
                    <div class="selected-images-grid">
                        <?php 
                        $cloudinary = new BR_Cloudinary_Integration();
                        foreach ($selected_images as $image): 
                            if (isset($image['id'])): 
                                $image_url = $cloudinary->generate_image_url($image['id'], array(
                                    'width' => 800,
                                    'height' => 800,
                                    'watermark' => true
                                ));
                        ?>
                                <div class="selected-image">
                                    <img src="<?php echo esc_url($image_url); ?>" alt="Selected image">
                                    <a href="<?php echo esc_url($image_url); ?>" target="_blank">View</a>
                                </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['donation_details_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['donation_details_meta_box_nonce'], 'donation_details_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only allow updating the verification status
        if (isset($_POST['verification_status'])) {
            update_post_meta($post_id, 'verification_status', sanitize_text_field($_POST['verification_status']));
        }
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        $screen = get_current_screen();
        if ('donation' !== $screen->post_type) {
            return;
        }

        wp_enqueue_style(
            'beautiful-rescues-admin',
            BR_PLUGIN_URL . 'public/css/admin.css',
            array(),
            BR_VERSION
        );
    }
} 