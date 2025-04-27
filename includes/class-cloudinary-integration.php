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
        // Only process uploads that are explicitly marked as Beautiful Rescues uploads
        if (!isset($upload['beautiful_rescues']) || $upload['beautiful_rescues'] !== true) {
            // This is not a Beautiful Rescues upload, return the original upload data
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
        // Get the post
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
            'width' => 1600,  // Increased from 800 to 1600 for higher resolution
            'height' => null,  // Let height be determined by aspect ratio
            'crop' => 'scale', // Use scale instead of fill to preserve aspect ratio
            'quality' => 'auto:best', // Changed to auto:best for better quality
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

        // Add width and crop settings
        $transformations[] = "c_{$options['crop']},w_{$options['width']}";
        
        // Only add height if explicitly set
        if ($options['height']) {
            $transformations[] = "h_{$options['height']}";
        }

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
        $this->debug->log('Generated Cloudinary URL', [
            'url' => $url,
            'public_id' => $public_id,
            'transformations' => $transformations_string
        ], 'info');
        
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
    public function get_images_from_folder($folder = 'Cats', $limit = 20, $sort = 'random', $page = 1) {
        if (!$this->init_cloudinary()) {
            $this->debug->log('Cloudinary not initialized', null, 'error');
            return [];
        }

        // Create transient keys for each sort option
        $transient_keys = [
            'random' => 'br_gallery_random_' . md5($folder),
            'newest' => 'br_gallery_newest_' . md5($folder),
            'oldest' => 'br_gallery_oldest_' . md5($folder),
            'name' => 'br_gallery_name_' . md5($folder)
        ];

        // Set a default key if the requested sort is not in our keys
        if (!isset($transient_keys[$sort])) {
            $this->debug->log('Unknown sort parameter, defaulting to random', [
                'requested_sort' => $sort,
                'available_sorts' => array_keys($transient_keys)
            ], 'warning');
            $sort = 'random';
        }

        // Check if we have cached results for the requested sort
        $transient_key = $transient_keys[$sort];
        $cached_result = get_transient($transient_key);
        
        if ($cached_result !== false) {
            $this->debug->log('Using cached results', [
                'folder' => $folder,
                'sort' => $sort,
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

            // Get all results for the category
            $result = $searchApi->expression($search_expression)
                ->maxResults(1000)
                ->execute();

            $resources = $result['resources'] ?? [];

            // Extract filenames from asset_folder for all resources
            foreach ($resources as &$resource) {
                $filename = '';
                if (!empty($resource['asset_folder'])) {
                    $this->debug->log('Processing asset_folder for filename', array(
                        'asset_folder' => $resource['asset_folder'],
                        'raw_data' => $resource
                    ), 'info');
                    
                    // Split by forward slashes and get the last part
                    $parts = explode('/', $resource['asset_folder']);
                    $filename = end($parts);
                    $this->debug->log('Final filename', array(
                        'filename' => $filename,
                        'parts' => $parts
                    ), 'info');
                }
                
                $resource['filename'] = $filename;
            }

            // Store sorted versions in separate transients
            foreach ($transient_keys as $sort_type => $key) {
                $sorted_resources = $resources;
                
                switch ($sort_type) {
                    case 'newest':
                        usort($sorted_resources, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        break;
                    case 'oldest':
                        usort($sorted_resources, function($a, $b) {
                            return strtotime($a['created_at']) - strtotime($b['created_at']);
                        });
                        break;
                    case 'name':
                        usort($sorted_resources, function($a, $b) {
                            // Use filename if available, otherwise use public_id
                            $a_name = !empty($a['filename']) ? $a['filename'] : $a['public_id'];
                            $b_name = !empty($b['filename']) ? $b['filename'] : $b['public_id'];
                            return strcasecmp($a_name, $b_name);
                        });
                        break;
                    case 'random':
                        shuffle($sorted_resources);
                        break;
                }
                
                // Cache each sorted version for 5 minutes
                set_transient($key, $sorted_resources, 5 * MINUTE_IN_SECONDS);
            }

            // Get the requested sort from cache
            $cached_result = get_transient($transient_key);
            if ($cached_result === false) {
                $this->debug->log('Failed to retrieve cached sort', [
                    'sort' => $sort,
                    'key' => $transient_key
                ], 'error');
                return [];
            }

            // Return paginated results
            $offset = ($page - 1) * $limit;
            $paginated_results = array_slice($cached_result, $offset, $limit);

            // Apply transformations to paginated results
            foreach ($paginated_results as &$image) {
                $image['url'] = $this->generate_image_url($image['public_id']);
            }

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
        // Only mark uploads as Beautiful Rescues if they come from our verification form
        if (isset($_POST['beautiful_rescues']) && $_POST['beautiful_rescues'] === '1') {
            $uploads['beautiful_rescues'] = true;
            $this->debug->log('Identified Beautiful Rescues upload from form', $uploads);
        }
        
        return $uploads;
    }

    /**
     * Get Cloudinary image source
     */
    public function get_cloudinary_image_src($image_src, $attachment_id, $size, $icon) {
        // Get the post
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

        return $image_src;
    }
} 