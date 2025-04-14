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
            array('jquery', 'elementor-frontend'),
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
            'max_width' => '100%',
            'category' => '',
            'sort' => 'name',
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
                .beautiful-rescues-gallery {
                    max-width: {$atts['max_width']};
                    margin: 0 auto;
                }
                
                .gallery-grid {
                    column-count: {$columns};
                    column-gap: {$atts['gutter']};
                    position: relative;
                }
                
                .gallery-item {
                    break-inside: avoid;
                    margin-bottom: 1.5rem;
                    width: 100%;
                    position: relative;
                    transition: all 0.3s ease;
                    opacity: 0;
                    transform: scale(0.95);
                    animation: gallery-item-fade-in 0.5s ease forwards;
                }

                @keyframes gallery-item-fade-in {
                    to {
                        opacity: 1;
                        transform: scale(1);
                    }
                }

                .gallery-item-skeleton {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: skeleton-loading 1.5s infinite;
                    z-index: 0;
                    pointer-events: none;
                    border-radius: 10px;
                }

                @keyframes skeleton-loading {
                    0% {
                        background-position: 200% 0;
                    }
                    100% {
                        background-position: -200% 0;
                    }
                }

                .gallery-item-image {
                    position: relative;
                    width: 100%;
                    overflow: hidden;
                    border-radius: 10px;
                    z-index: 1;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .gallery-item-image.loaded {
                    opacity: 1;
                }

                .gallery-item-image img {
                    width: 100%;
                    height: auto;
                    display: block;
                    object-fit: cover;
                    transition: transform 0.3s ease;
                    opacity: 0;
                    transform: scale(0.98);
                    transition: opacity 0.3s ease, transform 0.3s ease;
                }

                .gallery-item-image img.loaded {
                    opacity: 1;
                    transform: scale(1);
                }
                
                @media (max-width: {$atts['tablet_breakpoint']}) {
                    .gallery-grid {
                        column-count: {$atts['tablet_columns']};
                    }
                }
                
                @media (max-width: {$atts['mobile_breakpoint']}) {
                    .gallery-grid {
                        column-count: {$atts['mobile_columns']};
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
            // Extract filename from asset_folder
            $filename = '';
            if (!empty($image['asset_folder'])) {
                $debug->log('Processing asset_folder for filename', array(
                    'asset_folder' => $image['asset_folder'],
                    'raw_data' => $image
                ), 'info');
                
                // Split by forward slashes and get the last part
                $parts = explode('/', $image['asset_folder']);
                $filename = end($parts);
                $debug->log('Final filename', array(
                    'filename' => $filename,
                    'parts' => $parts
                ), 'info');
            }

            // Calculate aspect ratio for responsive sizing
            $aspect_ratio = !empty($image['height']) && !empty($image['width']) 
                ? ($image['height'] / $image['width']) * 100 
                : 100;
            
            $is_portrait = $aspect_ratio > 100;

            // Generate responsive image URLs with higher quality
            $responsive_urls = array(
                'thumbnail' => str_replace('/upload/', '/upload/w_' . ($is_portrait ? '600' : '800') . ',c_scale,q_auto:best,dpr_auto/', $image['url']),
                'medium' => str_replace('/upload/', '/upload/w_' . ($is_portrait ? '1200' : '1600') . ',c_scale,q_auto:best,dpr_auto/', $image['url']),
                'large' => str_replace('/upload/', '/upload/w_' . ($is_portrait ? '2000' : '2400') . ',c_scale,q_auto:best,dpr_auto/', $image['url']),
                'full' => $image['url']
            );

            // Create srcset string with proper width descriptors
            $srcset = implode(', ', array(
                $responsive_urls['thumbnail'] . ' 600w',
                $responsive_urls['medium'] . ' 1200w',
                $responsive_urls['large'] . ' 2000w',
                $responsive_urls['full'] . ' 2400w'
            ));

            // Determine sizes based on aspect ratio
            $sizes = $is_portrait
                ? '(max-width: 480px) 500px, (max-width: 768px) 800px, 1200px'
                : '(max-width: 480px) 600px, (max-width: 768px) 1000px, 1600px';

            $image['responsive_data'] = array(
                'urls' => $responsive_urls,
                'srcset' => $srcset,
                'sizes' => $sizes,
                'aspect_ratio' => $aspect_ratio,
                'is_portrait' => $is_portrait,
                'filename' => $filename,
                'watermarked_url' => $image['url'],
                'original_url' => $image['secure_url']
            );
        }
        
        if (!empty($initial_images)) {
            foreach ($initial_images as $image) {
                if (empty($image['url'])) continue;
                
                $initial_images_html .= '
                <div class="gallery-item" data-public-id="' . esc_attr($image['public_id']) . '" data-aspect-ratio="' . esc_attr($image['responsive_data']['aspect_ratio']) . '">
                    <div class="gallery-item-skeleton" style="padding-top: ' . esc_attr($image['responsive_data']['aspect_ratio']) . '%"></div>
                    <div class="gallery-item-image">
                        <img src="' . esc_url($image['responsive_data']['urls']['medium']) . '" 
                             srcset="' . esc_attr($image['responsive_data']['srcset']) . '"
                             sizes="' . esc_attr($image['responsive_data']['sizes']) . '"
                             alt="' . esc_attr($image['responsive_data']['filename'] ?? 'Gallery image') . '" 
                             data-watermarked-url="' . esc_url($image['responsive_data']['watermarked_url']) . '"
                             data-original-url="' . esc_url($image['responsive_data']['original_url']) . '"
                             data-width="' . esc_attr($image['width'] ?? '') . '"
                             data-height="' . esc_attr($image['height'] ?? '') . '"
                             loading="lazy"
                             onload="this.classList.add(\'loaded\'); this.parentElement.classList.add(\'loaded\'); this.parentElement.parentElement.querySelector(\'.gallery-item-skeleton\').style.display=\'none\'">
                        <div class="gallery-item-filename">' . esc_html($image['responsive_data']['filename'] ?? '') . '</div>
                        <div class="gallery-item-actions">
                            <button class="gallery-item-button select-button" aria-label="Select image">
                                <svg class="checkmark-icon" viewBox="0 0 24 24" width="24" height="24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
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
            <div class="gallery-notice">
                <p><?php _e('We serve high-resolution images to showcase the finest details. Please be patient while images load for the best viewing experience.', 'beautiful-rescues'); ?></p>
            </div>
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
                <button class="modal-close" aria-label="<?php _e('Close modal', 'beautiful-rescues'); ?>">
                    <svg viewBox="0 0 24 24" width="24" height="24">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
                <div class="modal-image-container">
                    <img class="modal-image" src="" alt="">
                    <div class="modal-caption"></div>
                </div>
                <div class="modal-navigation">
                    <button class="modal-nav-button" data-direction="-1" aria-label="<?php _e('Previous image', 'beautiful-rescues'); ?>">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                    <button class="modal-nav-button" data-direction="1" aria-label="<?php _e('Next image', 'beautiful-rescues'); ?>">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                        </svg>
                    </button>
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
            // Extract filename from asset_folder
            $filename = '';
            if (!empty($image['asset_folder'])) {
                $debug->log('Processing asset_folder for filename', array(
                    'asset_folder' => $image['asset_folder'],
                    'raw_data' => $image
                ), 'info');
                
                // Split by forward slashes and get the last part
                $parts = explode('/', $image['asset_folder']);
                $filename = end($parts);
                $debug->log('Final filename', array(
                    'filename' => $filename,
                    'parts' => $parts
                ), 'info');
            }
            
            $image['url'] = $this->cloudinary->generate_image_url($image['public_id']);
            $image['filename'] = $filename;
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