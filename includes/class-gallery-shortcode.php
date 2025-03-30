<?php
/**
 * Gallery Shortcode Class
 */
class BR_Gallery_Shortcode {
    private $cloudinary;

    public function __construct() {
        $this->cloudinary = new BR_Cloudinary_Integration();
        add_shortcode('beautiful_rescues_gallery', array($this, 'render_gallery'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_load_gallery_images', array($this, 'ajax_load_images'));
        add_action('wp_ajax_nopriv_load_gallery_images', array($this, 'ajax_load_images'));
    }

    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'beautiful-rescues-gallery',
            BR_PLUGIN_URL . 'public/css/gallery.css',
            array(),
            BR_VERSION
        );

        wp_enqueue_script(
            'beautiful-rescues-gallery',
            BR_PLUGIN_URL . 'public/js/gallery.js',
            array('jquery'),
            BR_VERSION,
            true
        );

        wp_localize_script('beautiful-rescues-gallery', 'beautifulRescuesGallery', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_gallery_nonce'),
            'maxFileSize' => (int) (get_option('beautiful_rescues_options')['max_file_size'] ?? 5) * 1024 * 1024,
            'i18n' => array(
                'loadMore' => __('Load More', 'beautiful-rescues'),
                'noMoreImages' => __('No more images to load', 'beautiful-rescues'),
                'previous' => __('Previous', 'beautiful-rescues'),
                'next' => __('Next', 'beautiful-rescues'),
                'close' => __('Close', 'beautiful-rescues')
            )
        ));
    }

    /**
     * Render the gallery shortcode
     */
    public function render_gallery($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'columns' => 3,
            'watermark' => true,
            'form_id' => 'donation-verification-form',
            'sort' => 'random',
            'per_page' => 20,
            'page' => 1
        ), $atts);

        // Get the current page from URL if not provided
        if (!isset($atts['page'])) {
            $atts['page'] = isset($_GET['gallery_page']) ? intval($_GET['gallery_page']) : 1;
        }

        // Get images with pagination
        $images = $this->cloudinary->get_images_from_folder($atts['category'], $atts['per_page'], $atts['sort']);
        
        if (empty($images)) {
            return '<div class="beautiful-rescues-gallery"><p>' . __('No images found.', 'beautiful-rescues') . '</p></div>';
        }

        ob_start();
        ?>
        <div class="beautiful-rescues-gallery" 
             data-columns="<?php echo esc_attr($atts['columns']); ?>"
             data-form-id="<?php echo esc_attr($atts['form_id']); ?>"
             data-category="<?php echo esc_attr($atts['category']); ?>"
             data-sort="<?php echo esc_attr($atts['sort']); ?>"
             data-page="<?php echo esc_attr($atts['page']); ?>"
             data-per-page="<?php echo esc_attr($atts['per_page']); ?>">
            
            <!-- Gallery Controls -->
            <div class="gallery-controls">
                <div class="gallery-sort">
                    <label for="gallery-sort"><?php _e('Sort by:', 'beautiful-rescues'); ?></label>
                    <select id="gallery-sort" class="gallery-sort-select">
                        <option value="random" <?php selected($atts['sort'], 'random'); ?>><?php _e('Random', 'beautiful-rescues'); ?></option>
                        <option value="newest" <?php selected($atts['sort'], 'newest'); ?>><?php _e('Newest First', 'beautiful-rescues'); ?></option>
                        <option value="oldest" <?php selected($atts['sort'], 'oldest'); ?>><?php _e('Oldest First', 'beautiful-rescues'); ?></option>
                        <option value="name" <?php selected($atts['sort'], 'name'); ?>><?php _e('Name', 'beautiful-rescues'); ?></option>
                    </select>
                </div>
                <div class="gallery-actions">
                    <button class="load-more-button" data-page="<?php echo esc_attr($atts['page']); ?>">
                        <?php _e('Load More', 'beautiful-rescues'); ?>
                    </button>
                    <button class="verify-donation-button" style="display: none;">
                        <?php _e('Verify Donation', 'beautiful-rescues'); ?>
                    </button>
                </div>
            </div>

            <!-- Gallery Grid -->
            <div class="gallery-grid">
                <?php foreach ($images as $image): ?>
                    <div class="gallery-item" data-public-id="<?php echo esc_attr($image['public_id']); ?>">
                        <div class="gallery-item-image">
                            <img src="<?php echo esc_url($this->cloudinary->generate_image_url($image['public_id'], array(
                                'width' => 800,
                                'height' => 800,
                                'watermark' => $atts['watermark']
                            ))); ?>" 
                                 alt="<?php echo esc_attr($image['filename']); ?>"
                                 data-url="<?php echo esc_url($image['url']); ?>">
                            <div class="gallery-item-overlay">
                                <div class="gallery-item-actions">
                                    <button class="gallery-item-button select-button">
                                        <?php _e('Select', 'beautiful-rescues'); ?>
                                    </button>
                                    <button class="gallery-item-button zoom-button">
                                        <?php _e('Zoom', 'beautiful-rescues'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($image['context']['caption'])): ?>
                            <div class="gallery-caption">
                                <?php echo esc_html($image['context']['caption']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Image Preview Modal -->
            <div class="gallery-modal" style="display: none;">
                <div class="modal-content">
                    <button class="modal-close">&times;</button>
                    <div class="modal-image-container">
                        <img src="" alt="" class="modal-image">
                    </div>
                    <div class="modal-navigation">
                        <button class="modal-nav-button modal-prev"><?php _e('Previous', 'beautiful-rescues'); ?></button>
                        <button class="modal-nav-button modal-next"><?php _e('Next', 'beautiful-rescues'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Donation Verification Modal -->
            <div class="donation-modal" style="display: none;">
                <div class="donation-modal-content">
                    <button class="modal-close">&times;</button>
                    <div class="donation-modal-header">
                        <h2><?php _e('Verify Your Donation', 'beautiful-rescues'); ?></h2>
                        <p><?php _e('Please review your selected images and provide your donation details.', 'beautiful-rescues'); ?></p>
                    </div>
                    
                    <div class="selected-images-preview">
                        <h3><?php _e('Selected Images', 'beautiful-rescues'); ?></h3>
                        <div class="selected-images-grid"></div>
                    </div>

                    <form id="<?php echo esc_attr($atts['form_id']); ?>" class="donation-verification-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="firstName"><?php _e('First Name', 'beautiful-rescues'); ?> *</label>
                            <input type="text" id="firstName" name="firstName" required>
                        </div>

                        <div class="form-group">
                            <label for="lastName"><?php _e('Last Name', 'beautiful-rescues'); ?> *</label>
                            <input type="text" id="lastName" name="lastName" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><?php _e('Email Address', 'beautiful-rescues'); ?> *</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="phone"><?php _e('Phone Number', 'beautiful-rescues'); ?> *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>

                        <div class="form-group">
                            <label for="donationVerification"><?php _e('Donation Verification (Image or PDF)', 'beautiful-rescues'); ?> *</label>
                            <input type="file" id="donationVerification" name="donationVerification" accept="image/*,.pdf" required>
                            <small class="form-help"><?php _e('Upload a screenshot or PDF of your donation receipt', 'beautiful-rescues'); ?></small>
                        </div>

                        <div class="form-group">
                            <label for="message"><?php _e('Message (Optional)', 'beautiful-rescues'); ?></label>
                            <textarea id="message" name="message" rows="4"></textarea>
                        </div>

                        <input type="hidden" name="selected_images" id="selected-cloudinary-images" value="">
                        
                        <button type="submit" class="submit-donation"><?php _e('Submit Donation', 'beautiful-rescues'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for loading more images
     */
    public function ajax_load_images() {
        check_ajax_referer('beautiful_rescues_gallery_nonce', 'nonce');

        $category = sanitize_text_field($_POST['category'] ?? '');
        $sort = sanitize_text_field($_POST['sort'] ?? 'random');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        $images = $this->cloudinary->get_images_from_folder($category, $per_page, $sort, $page);

        // Check if there are more images
        $has_more = count($images) === $per_page;

        wp_send_json_success(array(
            'images' => $images,
            'has_more' => $has_more
        ));
    }
} 