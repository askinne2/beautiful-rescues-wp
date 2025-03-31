<?php
/**
 * Verification Post Type Class
 */
class BR_Verification_Post_Type {
    public function __construct() {
        // Add meta boxes and other hooks
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // Add columns to admin list view
        add_filter('manage_verification_posts_columns', array($this, 'add_verification_columns'));
        add_action('manage_verification_posts_custom_column', array($this, 'display_verification_columns'), 10, 2);
        add_filter('manage_edit-verification_sortable_columns', array($this, 'make_verification_columns_sortable'));
    }

    /**
     * Add custom columns to verification list
     */
    public function add_verification_columns($columns) {
        $new_columns = array();
        foreach($columns as $key => $value) {
            if($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['first_name'] = __('First Name', 'beautiful-rescues');
                $new_columns['last_name'] = __('Last Name', 'beautiful-rescues');
                $new_columns['email'] = __('Email', 'beautiful-rescues');
                $new_columns['phone'] = __('Phone', 'beautiful-rescues');
                $new_columns['submission_date'] = __('Submission Date', 'beautiful-rescues');
                $new_columns['status'] = __('Status', 'beautiful-rescues');
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }

    /**
     * Display custom column content
     */
    public function display_verification_columns($column, $post_id) {
        switch($column) {
            case 'first_name':
                echo esc_html(get_post_meta($post_id, '_first_name', true));
                break;
            case 'last_name':
                echo esc_html(get_post_meta($post_id, '_last_name', true));
                break;
            case 'email':
                echo esc_html(get_post_meta($post_id, '_email', true));
                break;
            case 'phone':
                echo esc_html(get_post_meta($post_id, '_phone', true));
                break;
            case 'submission_date':
                echo esc_html(get_post_meta($post_id, '_submission_date', true));
                break;
            case 'status':
                echo esc_html(get_post_meta($post_id, '_status', true));
                break;
        }
    }

    /**
     * Make custom columns sortable
     */
    public function make_verification_columns_sortable($columns) {
        $columns['first_name'] = 'first_name';
        $columns['last_name'] = 'last_name';
        $columns['email'] = 'email';
        $columns['phone'] = 'phone';
        $columns['submission_date'] = 'submission_date';
        $columns['status'] = 'status';
        return $columns;
    }

    /**
     * Add meta boxes for verification details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'verification_details',
            __('Verification Details', 'beautiful-rescues'),
            array($this, 'render_verification_details_meta_box'),
            'verification',
            'normal',
            'high'
        );
    }

    /**
     * Render verification details meta box
     */
    public function render_verification_details_meta_box($post) {
        // Get meta fields
        $first_name = get_post_meta($post->ID, '_first_name', true);
        $last_name = get_post_meta($post->ID, '_last_name', true);
        $email = get_post_meta($post->ID, '_email', true);
        $phone = get_post_meta($post->ID, '_phone', true);
        $message = get_post_meta($post->ID, '_message', true);
        $verification_file = get_post_meta($post->ID, '_verification_file', true);
        $verification_file_path = get_post_meta($post->ID, '_verification_file_path', true);
        $selected_images = get_post_meta($post->ID, '_selected_images', true);
        $status = get_post_meta($post->ID, '_status', true);
        $submission_date = get_post_meta($post->ID, '_submission_date', true);
        
        // Add nonce for security
        wp_nonce_field('verification_details_meta_box', 'verification_details_meta_box_nonce');
        ?>
        <div class="verification-details-meta-box">
            <div class="verification-field">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="_first_name" value="<?php echo esc_attr($first_name); ?>" readonly>
            </div>
            
            <div class="verification-field">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="_last_name" value="<?php echo esc_attr($last_name); ?>" readonly>
            </div>
            
            <div class="verification-field">
                <label for="email">Email:</label>
                <input type="email" id="email" name="_email" value="<?php echo esc_attr($email); ?>" readonly>
            </div>
            
            <div class="verification-field">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="_phone" value="<?php echo esc_attr($phone); ?>" readonly>
            </div>
            
            <div class="verification-field">
                <label for="message">Message:</label>
                <textarea id="message" name="_message" readonly><?php echo esc_textarea($message); ?></textarea>
            </div>
            
            <div class="verification-field">
                <label>Verification File:</label>
                <?php if ($verification_file && $verification_file_path): ?>
                    <div class="verification-file-container">
                        <?php
                        $file_extension = strtolower(pathinfo($verification_file, PATHINFO_EXTENSION));
                        $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                        $file_url = wp_upload_dir()['baseurl'] . '/verifications/' . $verification_file;
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
            
            <div class="verification-field">
                <label for="status">Status:</label>
                <select id="status" name="_status">
                    <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                    <option value="verified" <?php selected($status, 'verified'); ?>>Verified</option>
                    <option value="rejected" <?php selected($status, 'rejected'); ?>>Rejected</option>
                </select>
            </div>
            
            <div class="verification-field">
                <label>Submission Date:</label>
                <input type="text" value="<?php echo esc_attr($submission_date); ?>" readonly>
            </div>
            
            <?php if (!empty($selected_images)): ?>
                <div class="verification-field">
                    <label>Selected Images:</label>
                    <div class="selected-images-grid">
                        <?php 
                        $cloudinary = new BR_Cloudinary_Integration();
                        foreach ($selected_images as $image): 
                            if (isset($image['id'])): 
                                $image_url = $cloudinary->generate_image_url($image['id'], array(
                                    'width' => 800,
                                    'height' => 800,
                                    'crop' => 'fill',
                                    'quality' => 'auto',
                                    'format' => 'auto'
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
        if (!isset($_POST['verification_details_meta_box_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['verification_details_meta_box_nonce'], 'verification_details_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only allow updating the status
        if (isset($_POST['_status'])) {
            update_post_meta($post_id, '_status', sanitize_text_field($_POST['_status']));
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
        if ('verification' !== $screen->post_type) {
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