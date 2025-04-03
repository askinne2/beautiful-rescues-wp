<?php
/**
 * Cloudinary Integration Class
 */
class BR_Cloudinary_Integration {
    private $cloud_name;
    private $api_key;
    private $api_secret;
    private $folder;
    private $debug;
    private $cloudinary;

    // Define available cat folders
    private $cat_folders = [
        "Black",
        "BlackWhite",
        "Calico",
        "Ginger",
        "Grey",
        "Mixed",
        "Multiple",
        "Pointed",
        "Seasonal",
        "Special",
        "Tabby",
        "Tortie",
        "White"
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->debug = BR_Debug::get_instance();
        $this->debug->log('Initializing Cloudinary integration');
        
        // Get options
        $options = get_option('beautiful_rescues_options', array());
        $this->cloud_name = isset($options['cloudinary_cloud_name']) ? $options['cloudinary_cloud_name'] : '';
        $this->api_key = isset($options['cloudinary_api_key']) ? $options['cloudinary_api_key'] : '';
        $this->api_secret = isset($options['cloudinary_api_secret']) ? $options['cloudinary_api_secret'] : '';
        $this->folder = isset($options['cloudinary_folder']) ? $options['cloudinary_folder'] : 'beautiful-rescues';
        
        // Add hooks for media handling with high priority to run after other plugins
        add_filter('wp_handle_upload', array($this, 'handle_upload_to_cloudinary'), 99, 2);
        add_filter('wp_get_attachment_url', array($this, 'get_cloudinary_url'), 99, 2);
        
        // Add filter to identify Beautiful Rescues uploads
        add_filter('upload_dir', array($this, 'identify_br_uploads'), 10, 1);
        
        // Add filter for gallery images
        add_filter('wp_get_attachment_image_src', array($this, 'get_cloudinary_image_src'), 99, 4);
    }

    /**
     * Check if Cloudinary SDK is available
     */
    private function check_cloudinary_sdk() {
        return class_exists('\Cloudinary\Cloudinary');
    }

    /**
     * Initialize Cloudinary client
     */
    private function init_cloudinary() {
        if (!$this->check_cloudinary_sdk()) {
            $this->debug->log('Cloudinary SDK not available');
            return false;
        }

        try {
            $config = new \Cloudinary\Configuration\Configuration();
            $config->cloud->cloudName = $this->cloud_name;
            $config->cloud->apiKey = $this->api_key;
            $config->cloud->apiSecret = $this->api_secret;
            $config->url->secure = true;

            $this->cloudinary = new \Cloudinary\Cloudinary($config);
            
            // Test the configuration
            $test_result = $this->cloudinary->adminApi()->ping();
            $this->debug->log('Cloudinary initialized successfully');
            
            return true;
        } catch (Exception $e) {
            $this->debug->log('Cloudinary initialization failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle file upload to Cloudinary
     */
    public function handle_upload_to_cloudinary($upload, $context) {
        // Skip processing for WordPress Media Library uploads
        if ($context === 'upload' && isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            
            // Check if this is a WordPress Media Library upload
            if (strpos($referer, 'wp-admin/media-upload.php') !== false || 
                strpos($referer, 'wp-admin/media-new.php') !== false ||
                strpos($referer, 'wp-admin/upload.php') !== false) {
                $this->debug->log('Bypassing Cloudinary for WordPress Media Library upload', array('referer' => $referer));
                return $upload;
            }
        }
        
        // Check if this is a Beautiful Rescues upload
        $is_br_upload = false;
        
        // Method 1: Check if it's identified as a BR upload
        if (isset($upload['beautiful_rescues']) && $upload['beautiful_rescues'] === true) {
            $is_br_upload = true;
        }
        
        // Method 2: Check referer
        if (!$is_br_upload && isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            $is_br_upload = strpos($referer, 'beautiful-rescues') !== false;
        }
        
        // Method 3: Check if it's a verification post type
        if (!$is_br_upload && isset($_POST['post_id'])) {
            $post_type = get_post_type($_POST['post_id']);
            $is_br_upload = $post_type === 'verification';
        }
        
        // If not from our plugin, return the upload as is
        if (!$is_br_upload) {
            return $upload;
        }

        // Ensure we have a valid file
        if (!isset($upload['file']) || empty($upload['file'])) {
            $this->debug->log('Invalid upload data: missing file', $upload);
            return $upload;
        }

        if (!$this->init_cloudinary()) {
            $this->debug->log('Failed to initialize Cloudinary for upload');
            return $upload;
        }

        try {
            $uploadApi = $this->cloudinary->uploadApi();
            $result = $uploadApi->upload($upload['file'], [
                'folder' => $this->folder,
                'resource_type' => 'auto'
            ]);

            $this->debug->log('Cloudinary upload successful', $result);

            return array(
                'public_id' => $result['public_id'],
                'url' => $result['secure_url']
            );
        } catch (Exception $e) {
            $this->debug->log('Cloudinary upload error: ' . $e->getMessage());
            return $upload; // Return original upload on error
        }
    }

    /**
     * Get Cloudinary URL for an attachment
     */
    public function get_cloudinary_url($url, $attachment_id) {
        // Skip processing for WordPress Media Library URLs
        if (isset($_SERVER['REQUEST_URI']) && 
            (strpos($_SERVER['REQUEST_URI'], 'wp-admin/media-upload.php') !== false || 
             strpos($_SERVER['REQUEST_URI'], 'wp-admin/media-new.php') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'wp-admin/upload.php') !== false)) {
            return $url;
        }
        
        // Check if this is a Beautiful Rescues attachment
        $post = get_post($attachment_id);
        if (!$post) {
            return $url;
        }
        
        // Check if this is a verification post type
        if ($post->post_type === 'verification') {
            return $url; // Don't process verification files
        }
        
        // Check if this is a Beautiful Rescues gallery image
        $is_br_image = false;
        
        // Method 1: Check if it has our meta
        $cloudinary_public_id = get_post_meta($attachment_id, '_cloudinary_public_id', true);
        if (!empty($cloudinary_public_id)) {
            $is_br_image = true;
        }
        
        // Method 2: Check if it's in our gallery
        if (!$is_br_image) {
            $selected_images = get_post_meta($attachment_id, '_selected_images', true);
            if (!empty($selected_images)) {
                $is_br_image = true;
            }
        }
        
        // If not from our plugin, return the original URL
        if (!$is_br_image) {
            return $url;
        }

        // Get Cloudinary public ID
        $public_id = get_post_meta($attachment_id, '_cloudinary_public_id', true);
        if (empty($public_id)) {
            $this->debug->log('No Cloudinary public ID found for attachment', array('attachment_id' => $attachment_id));
            return $url;
        }

        // Generate Cloudinary URL
        return $this->generate_image_url($public_id);
    }

    /**
     * Generate Cloudinary URL with transformations
     */
    public function generate_image_url($public_id, $options = array()) {
        // Default options
        $default_options = array(
            'width' => 800,
            'height' => 800,
            'crop' => 'fill',
            'quality' => 'auto',
            'format' => 'auto',
            'watermark' => true,
            'responsive' => true,
            'webp' => true
        );

        // Merge with provided options
        $options = wp_parse_args($options, $default_options);

        // Build transformations array
        $transformations = array();

        // Add responsive sizes if enabled
        if ($options['responsive']) {
            $transformations[] = "w_auto,dpr_auto";
        }

        // Add crop settings
        $transformations[] = "c_{$options['crop']},w_{$options['width']},h_{$options['height']}";

        // Add quality setting
        $transformations[] = "q_{$options['quality']}";
        
        // Add WebP support with fallback
        if ($options['webp']) {
            $transformations[] = "f_auto";
        } else {
            $transformations[] = "f_{$options['format']}";
        }

        // Add watermark if enabled (after other transformations)
        if ($options['watermark'] && get_option('enable_watermark')) {
            $watermark_url = get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356913/br-watermark-2025_2x_uux1x2.webp');
            // Extract the public ID from the watermark URL
            if (preg_match('/\/v\d+\/([^\/]+)\.(webp|png|jpg|jpeg)$/', $watermark_url, $matches)) {
                $watermark_public_id = $matches[1];
                $transformations[] = "l_{$watermark_public_id},w_0.7,o_50,fl_relative/fl_tiled.layer_apply";
            }
        }

        // Join transformations with forward slashes
        $transformations_string = implode('/', $transformations);

        $url = "https://res.cloudinary.com/{$this->cloud_name}/image/upload/{$transformations_string}/{$public_id}";
        
        $this->debug->log('Generated Cloudinary URL', array(
            'public_id' => $public_id,
            'options' => $options,
            'transformations' => $transformations,
            'url' => $url
        ), 'info');

        return $url;
    }

    /**
     * Get available cat folders
     */
    public function get_cat_folders() {
        return $this->cat_folders;
    }

    /**
     * Get images from a specific folder with rate limiting
     */
    public function get_images_from_folder($folder = '', $limit = 20, $sort = 'random', $page = 1) {
        if (!$this->init_cloudinary()) {
            $this->debug->log('Cloudinary not initialized', null, 'error');
            return [];
        }

        // Create a transient key for the full category results
        $transient_key = 'br_gallery_full_' . md5($folder . $sort);
        $cached_result = get_transient($transient_key);
        
        if ($cached_result !== false) {
            $this->debug->log('Using cached results', [
                'folder' => $folder,
                'total_count' => count($cached_result),
                'page' => $page,
                'limit' => $limit
            ], 'info');
            
            // Calculate pagination from cached results
            $offset = ($page - 1) * $limit;
            $paginated_results = array_slice($cached_result, $offset, $limit);
            
            // Apply transformations to paginated results
            foreach ($paginated_results as &$image) {
                $image['url'] = $this->generate_image_url($image['public_id']);
            }
            
            $this->debug->log('Returning paginated results from cache', [
                'count' => count($paginated_results),
                'offset' => $offset,
                'limit' => $limit
            ], 'info');
            
            return $paginated_results;
        }

        try {
            $searchApi = $this->cloudinary->searchApi();
            
            // Start with base expression
            $search_expression = 'resource_type:image';
            
            if ($folder) {
                // If it's a cat folder, use the Cats/ prefix
                if (in_array($folder, $this->cat_folders)) {
                    $search_expression .= " AND folder:Cats/{$folder}/*";
                } else if ($folder === 'Cats') {
                    $search_expression .= " AND folder:Cats/*";
                } else {
                    $search_expression .= " AND folder:{$folder}/*";
                }
            } else {
                $search_expression .= " AND folder:Cats/*";
            }

            // Add sorting
            switch ($sort) {
                case 'newest':
                    $search_expression .= " ORDER BY created_at DESC";
                    break;
                case 'oldest':
                    $search_expression .= " ORDER BY created_at ASC";
                    break;
                case 'name':
                    $search_expression .= " ORDER BY filename ASC";
                    break;
                case 'random':
                default:
                    // Random sorting handled after fetching
                    break;
            }

            $this->debug->log('Executing Cloudinary search', [
                'expression' => $search_expression,
                'folder' => $folder,
                'sort' => $sort
            ], 'info');

            // Get all results for the category
            $result = $searchApi->expression($search_expression)
                ->maxResults(1000) // Adjust this based on your needs
                ->execute();

            $resources = $result['resources'] ?? [];

            // Handle random sorting if needed
            if ($sort === 'random' || !$folder || $folder === 'Cats') {
                // Group images by category
                $images_by_category = array();
                foreach ($resources as $resource) {
                    $category = explode('/', $resource['asset_folder'])[1] ?? 'uncategorized';
                    if (!isset($images_by_category[$category])) {
                        $images_by_category[$category] = array();
                    }
                    $images_by_category[$category][] = $resource;
                }

                $this->debug->log('Images grouped by category', array_map('count', $images_by_category), 'info');

                // Get random images from each category
                $categories = array_keys($images_by_category);
                $images_per_category = ceil(count($resources) / count($categories));
                
                $selected_images = array();
                foreach ($categories as $category) {
                    $category_images = $images_by_category[$category];
                    shuffle($category_images);
                    $selected_images = array_merge(
                        $selected_images,
                        array_slice($category_images, 0, $images_per_category)
                    );
                }

                // Final shuffle
                shuffle($selected_images);
                $resources = $selected_images;
            }

            // Cache the full results for 5 minutes
            set_transient($transient_key, $resources, 5 * MINUTE_IN_SECONDS);

            $this->debug->log('Cached full results', [
                'total_count' => count($resources),
                'folder' => $folder,
                'sort' => $sort
            ], 'info');

            // Return paginated results
            $offset = ($page - 1) * $limit;
            $paginated_results = array_slice($resources, $offset, $limit);

            // Apply transformations to paginated results
            foreach ($paginated_results as &$image) {
                $image['url'] = $this->generate_image_url($image['public_id']);
            }

            $this->debug->log('Returning paginated results', [
                'count' => count($paginated_results),
                'offset' => $offset,
                'limit' => $limit
            ], 'info');

            return $paginated_results;
        } catch (Exception $e) {
            $this->debug->log('Cloudinary search error', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ], 'error');
            return [];
        }
    }

    /**
     * Get total count of images in a folder
     */
    public function get_total_images_count($folder = 'Cats') {
        if (!$this->init_cloudinary()) {
            $this->debug->log('Cloudinary not initialized for counting images', null, 'error');
            return 0;
        }

        try {
            $searchApi = $this->cloudinary->searchApi();
            
            // Build search expression
            $search_expression = 'resource_type:image';
            
            if ($folder) {
                // If it's a cat folder, use the Cats/ prefix
                if (in_array($folder, $this->cat_folders)) {
                    $search_expression .= " AND folder:Cats/{$folder}/*";
                } else if ($folder === 'Cats') {
                    $search_expression .= " AND folder:Cats/*";
                } else {
                    $search_expression .= " AND folder:{$folder}/*";
                }
            } else {
                $search_expression .= " AND folder:Cats/*";
            }

            $this->debug->log('Counting images with expression', [
                'expression' => $search_expression,
                'folder' => $folder
            ], 'info');

            // Get total count
            $result = $searchApi->expression($search_expression)
                ->maxResults(1)
                ->execute();

            $total_count = $result['total_count'] ?? 0;

            $this->debug->log('Total images count retrieved', [
                'count' => $total_count,
                'folder' => $folder
            ], 'info');

            return $total_count;
        } catch (Exception $e) {
            $this->debug->log('Error counting images', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ], 'error');
            return 0;
        }
    }

    /**
     * Identify Beautiful Rescues uploads
     */
    public function identify_br_uploads($uploads) {
        // Check if this is a Beautiful Rescues upload
        $is_br_upload = false;
        
        // Method 1: Check POST data
        if (isset($_POST['beautiful_rescues']) && $_POST['beautiful_rescues'] === '1') {
            $is_br_upload = true;
        }
        
        // Method 2: Check referer
        if (!$is_br_upload && isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            $is_br_upload = strpos($referer, 'beautiful-rescues') !== false;
        }
        
        // Method 3: Check if it's a verification post type
        if (!$is_br_upload && isset($_POST['post_id'])) {
            $post_type = get_post_type($_POST['post_id']);
            $is_br_upload = $post_type === 'verification';
        }
        
        // If this is a Beautiful Rescues upload, add our identifier
        if ($is_br_upload) {
            $uploads['beautiful_rescues'] = true;
            $this->debug->log('Identified Beautiful Rescues upload', $uploads);
        }
        
        return $uploads;
    }

    /**
     * Get Cloudinary image source
     */
    public function get_cloudinary_image_src($image_src, $attachment_id, $size, $icon) {
        // Skip processing for WordPress Media Library image sources
        if (isset($_SERVER['REQUEST_URI']) && 
            (strpos($_SERVER['REQUEST_URI'], 'wp-admin/media-upload.php') !== false || 
             strpos($_SERVER['REQUEST_URI'], 'wp-admin/media-new.php') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'wp-admin/upload.php') !== false)) {
            return $image_src;
        }
        
        // Check if this is a Beautiful Rescues attachment
        $post = get_post($attachment_id);
        if (!$post) {
            return $image_src;
        }
        
        // Check if this is a verification post type
        if ($post->post_type === 'verification') {
            return $image_src; // Don't process verification files
        }
        
        // Check if this is a Beautiful Rescues gallery image
        $is_br_image = false;
        
        // Method 1: Check if it has our meta
        $cloudinary_public_id = get_post_meta($attachment_id, '_cloudinary_public_id', true);
        if (!empty($cloudinary_public_id)) {
            $is_br_image = true;
        }
        
        // Method 2: Check if it's in our gallery
        if (!$is_br_image) {
            $selected_images = get_post_meta($attachment_id, '_selected_images', true);
            if (!empty($selected_images)) {
                $is_br_image = true;
            }
        }
        
        // If not from our plugin, return the original image source
        if (!$is_br_image) {
            return $image_src;
        }

        // Get Cloudinary public ID
        $public_id = get_post_meta($attachment_id, '_cloudinary_public_id', true);
        if (empty($public_id)) {
            $this->debug->log('No Cloudinary public ID found for attachment', array('attachment_id' => $attachment_id));
            return $image_src;
        }

        // Generate Cloudinary image source
        $image_src = $this->generate_image_url($public_id);
        
        $this->debug->log('Generated Cloudinary image source', array(
            'public_id' => $public_id,
            'image_src' => $image_src
        ), 'info');

        return $image_src;
    }
} 