<?php
/**
 * Gallery Shortcode Class
 */
class BR_Gallery_Shortcode {
    private $cloudinary;

    public function __construct() {
        $this->cloudinary = new BR_Cloudinary_Integration();
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_load_gallery_images', array($this, 'ajax_load_images'));
        add_action('wp_ajax_nopriv_load_gallery_images', array($this, 'ajax_load_images'));
    }

    /**
     * Enqueue required scripts and styles
     */
    public function enqueue_scripts() {
        // Add Elementor dependency if Elementor is active
        $dependencies = defined('ELEMENTOR_VERSION') ? array('elementor-frontend') : array();
        
        wp_enqueue_style(
            'beautiful-rescues-gallery',
            BR_PLUGIN_URL . 'public/css/gallery.css',
            $dependencies,
            BR_VERSION
        );

        wp_enqueue_script(
            'beautiful-rescues-gallery',
            BR_PLUGIN_URL . 'public/js/gallery.js',
            array('jquery'),
            BR_VERSION,
            true
        );

        // Prepare localization data
        $localization_data = array(
            'isElementorEditor' => defined('ELEMENTOR_VERSION') && isset($_GET['elementor-preview']),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('beautiful_rescues_gallery_nonce'),
            'maxFileSize' => (int) (get_option('beautiful_rescues_options')['max_file_size'] ?? 5) * 1024 * 1024,
            'watermarkUrl' => get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356913/br-watermark-2025_2x_uux1x2.webp'),
            'i18n' => array(
                'loadMore' => __('Load More', 'beautiful-rescues'),
                'noMoreImages' => __('No more images to load', 'beautiful-rescues'),
                'previous' => __('Previous', 'beautiful-rescues'),
                'next' => __('Next', 'beautiful-rescues'),
                'close' => __('Close', 'beautiful-rescues'),
                'select' => __('Select', 'beautiful-rescues'),
                'selected' => __('Selected', 'beautiful-rescues'),
                'zoom' => __('Zoom', 'beautiful-rescues')
            )
        );

        wp_localize_script('beautiful-rescues-gallery', 'beautifulRescuesGallery', $localization_data);
    }

    /**
     * Render the gallery shortcode
     */
    public function render_gallery($atts) {
        $debug = BR_Debug::get_instance();
        $debug->log('Rendering gallery shortcode', array(
            'atts' => $atts,
            'is_admin' => is_admin(),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ), 'info');

        $atts = shortcode_atts(array(
            'style' => 'default',
            'columns' => '5',
            'gutter' => '1.5rem',
            'max_width' => '1200px',
            'category' => '',
            'sort' => 'newest',
            'per_page' => '40',
            'tablet_breakpoint' => '768px',
            'mobile_breakpoint' => '480px',
            'tablet_columns' => '2',
            'mobile_columns' => '2'
        ), $atts);

        // Calculate the columns to ensure responsive grid
        $columns = intval($atts['columns']);
        if ($columns < 1) $columns = 3;

        // Add an inline style for the grid to ensure proper display
        $inline_style = "
            <style>
                .beautiful-rescues-gallery .gallery-grid {
                    grid-template-columns: repeat({$columns}, 1fr) !important;
                    gap: {$atts['gutter']} !important;
                }
                
                @media (max-width: {$atts['tablet_breakpoint']}) {
                    .beautiful-rescues-gallery .gallery-grid {
                        grid-template-columns: repeat({$atts['tablet_columns']}, 1fr) !important;
                    }
                }
                
                @media (max-width: {$atts['mobile_breakpoint']}) {
                    .beautiful-rescues-gallery .gallery-grid {
                        grid-template-columns: repeat({$atts['mobile_columns']}, 1fr) !important;
                    }
                }
            </style>
        ";

        // Pre-load initial images for immediate display
        $initial_images = $this->cloudinary->get_images_from_folder($atts['category'], intval($atts['per_page']), $atts['sort'], 1);
        $total_images = $this->cloudinary->get_total_images_count($atts['category']);
        $initial_images_html = '';
        
        // Apply transformations to each image
        foreach ($initial_images as &$image) {
            $image['url'] = $this->cloudinary->generate_image_url($image['public_id']);
        }
        
        if (!empty($initial_images)) {
            foreach ($initial_images as $image) {
                if (empty($image['url'])) continue;
                
                $imageUrl = $image['url'];
                $initial_images_html .= '
                <div class="gallery-item" data-public-id="' . esc_attr($image['public_id']) . '">
                    <div class="gallery-item-image">
                        <img src="' . esc_url($imageUrl) . '" alt="' . esc_attr($image['filename'] ?? 'Gallery image') . '" data-url="' . esc_url($imageUrl) . '">
                        <div class="gallery-item-actions">
                            <button class="gallery-item-button select-button" aria-label="Select image">
                                <svg class="radio-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <circle class="radio-circle" cx="12" cy="12" r="10"/>
                                    <circle class="radio-dot" cx="12" cy="12" r="4"/>
                                </svg>
                            </button>
                            <button class="gallery-item-button zoom-button" aria-label="Zoom image">
                                <svg class="zoom-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                ';
            }
        }
        
        // Add debug class to identify Elementor integration
        $elementor_classes = defined('ELEMENTOR_VERSION') ? 'elementor-widget elementor-widget-html' : '';
        
        ob_start();
        echo $inline_style;
        ?>
        <div class="beautiful-rescues-gallery <?php echo esc_attr($elementor_classes); ?>" 
             data-style="<?php echo esc_attr($atts['style']); ?>"
             data-columns="<?php echo esc_attr($atts['columns']); ?>"
             data-gutter="<?php echo esc_attr($atts['gutter']); ?>"
             data-max-width="<?php echo esc_attr($atts['max_width']); ?>"
             data-category="<?php echo esc_attr($atts['category']); ?>"
             data-sort="<?php echo esc_attr($atts['sort']); ?>"
             data-per-page="<?php echo esc_attr($atts['per_page']); ?>"
             data-total-images="<?php echo esc_attr($total_images); ?>">
            <!-- Gallery content -->
            <div class="gallery-controls">
                <div class="gallery-sort">
                    <select class="gallery-sort-select">
                        <option value="random" <?php selected($atts['sort'], 'random'); ?>><?php _e('Random', 'beautiful-rescues'); ?></option>
                        <option value="newest" <?php selected($atts['sort'], 'newest'); ?>><?php _e('Newest First', 'beautiful-rescues'); ?></option>
                        <option value="oldest" <?php selected($atts['sort'], 'oldest'); ?>><?php _e('Oldest First', 'beautiful-rescues'); ?></option>
                        <option value="name" <?php selected($atts['sort'], 'name'); ?>><?php _e('Name (A-Z)', 'beautiful-rescues'); ?></option>
                    </select>
                </div>
            </div>
            <div class="gallery-grid">
                <?php echo $initial_images_html; ?>
            </div>
            <?php if ($total_images > count($initial_images)): ?>
                <button class="load-more-button"><?php _e('Load More', 'beautiful-rescues'); ?></button>
            <?php endif; ?>
        </div>

        <!-- Gallery Modal -->
        <div class="gallery-modal">
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                <div class="modal-image-container">
                    <img class="modal-image" src="" alt="">
                    <div class="modal-caption"></div>
                </div>
                <div class="modal-navigation">
                    <button class="modal-nav-button" data-direction="-1"><?php _e('Previous', 'beautiful-rescues'); ?></button>
                    <button class="modal-nav-button" data-direction="1"><?php _e('Next', 'beautiful-rescues'); ?></button>
                </div>
            </div>
        </div>
        <?php
        $output = ob_get_clean();
        $debug->log('Gallery shortcode rendered', array(
            'output_length' => strlen($output),
            'has_content' => !empty($output),
            'initial_images_count' => count($initial_images),
            'total_images' => $total_images
        ), 'info');
        return $output;
    }

    /**
     * AJAX handler for loading more images
     */
    public function ajax_load_images() {
        $debug = BR_Debug::get_instance();

        $category = sanitize_text_field($_POST['category'] ?? '');
        $sort = sanitize_text_field($_POST['sort'] ?? 'random');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 20);

        $debug->log('Gallery AJAX request received', array(
            'category' => $category,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $per_page
        ), 'info');

        // Get all images for the category
        $images = $this->cloudinary->get_images_from_folder($category, 1000, 'newest', 1);
        $total_images = count($images);

        // Apply sorting on the client side
        switch ($sort) {
            case 'random':
                shuffle($images);
                break;
            case 'newest':
                usort($images, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                break;
            case 'oldest':
                usort($images, function($a, $b) {
                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                });
                break;
            case 'name':
                usort($images, function($a, $b) {
                    return strcasecmp($a['filename'], $b['filename']);
                });
                break;
        }

        // Apply pagination after sorting
        $offset = ($page - 1) * $per_page;
        $images = array_slice($images, $offset, $per_page);

        // Apply transformations to paginated results
        foreach ($images as &$image) {
            $image['url'] = $this->cloudinary->generate_image_url($image['public_id']);
        }

        // Check if there are more images
        $has_more = ($offset + $per_page) < $total_images;

        wp_send_json_success(array(
            'images' => $images,
            'total_images' => $total_images,
            'has_more' => $has_more
        ));
    }
} 