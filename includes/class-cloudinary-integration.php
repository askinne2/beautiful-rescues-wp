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

    public function __construct() {
        $this->debug = BR_Debug::get_instance();
        $options = get_option('beautiful_rescues_options');
        
        $this->cloud_name = $options['cloudinary_cloud_name'] ?? '';
        $this->api_key = $options['cloudinary_api_key'] ?? '';
        $this->api_secret = $options['cloudinary_api_secret'] ?? '';
        $this->folder = $options['cloudinary_folder'] ?? 'Cats';

        // Add hooks for media handling
        add_filter('wp_handle_upload', array($this, 'handle_upload_to_cloudinary'), 10, 2);
        add_filter('wp_getattachmenturl', array($this, 'get_cloudinary_url'), 10, 2);
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
        if ($context !== 'upload') {
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
            return false;
        }
    }

    /**
     * Get Cloudinary URL for attachment
     */
    public function get_cloudinary_url($url, $attachment_id) {
        $public_id = get_post_meta($attachment_id, '_cloudinary_public_id', true);
        
        if (!$public_id) {
            $this->debug->log('No Cloudinary public_id found for attachment: ' . $attachment_id);
            return $url;
        }

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
            'watermark' => true
        );

        // Merge with provided options
        $options = wp_parse_args($options, $default_options);

        // Build transformations array
        $transformations = array();

        // Add watermark if enabled
        if ($options['watermark'] && get_option('enable_watermark')) {
            $watermark_url = get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356913/br-watermark-2025_2x_uux1x2.webp');
            // Extract the public ID from the watermark URL
            if (preg_match('/\/v\d+\/([^\/]+)\.(webp|png|jpg|jpeg)$/', $watermark_url, $matches)) {
                $watermark_public_id = $matches[1];
                $transformations[] = "l_{$watermark_public_id},w_0.7,o_50,fl_relative/fl_tiled.layer_apply";
            }
        }

        // Add main image transformations last
        $transformations[] = "c_{$options['crop']},w_{$options['width']},h_{$options['height']}";
        $transformations[] = "q_{$options['quality']},f_{$options['format']}";

        // Join transformations with forward slashes
        $transformations_string = implode('/', $transformations);

        $url = "https://res.cloudinary.com/{$this->cloud_name}/image/upload/{$transformations_string}/{$public_id}";
        $this->debug->log('Generated Cloudinary URL', array(
            'public_id' => $public_id,
            'options' => $options,
            'transformations' => $transformations,
            'url' => $url
        ));

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

        // Rate limiting
        $transient_key = 'br_gallery_' . md5($folder . $sort . $page);
        $cached_result = get_transient($transient_key);
        
        if ($cached_result !== false) {
            $this->debug->log('Using cached results', [
                'folder' => $folder,
                'count' => count($cached_result)
            ]);
            return $cached_result;
        }

        try {
            $searchApi = $this->cloudinary->searchApi();
            
            // Start with base expression
            $search_expression = 'resource_type:image';
            
            if ($folder) {
                // If it's a cat folder, use the Cats/ prefix
                if (in_array($folder, $this->cat_folders)) {
                    // Add wildcard to include subfolders
                    $search_expression .= " AND folder:Cats/{$folder}/*";
                    $this->debug->log('Searching specific cat folder', [
                        'folder' => $folder,
                        'expression' => $search_expression
                    ]);
                } else if ($folder === 'Cats') {
                    $search_expression .= " AND folder:Cats/*";
                    $this->debug->log('Searching all cat folders', [
                        'expression' => $search_expression
                    ]);
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
                'max_results' => $folder ? $limit : 200,
                'folder' => $folder
            ]);

            // Calculate pagination
            $offset = ($page - 1) * $limit;
            
            // Get more results for better randomization when no category is selected
            $max_results = $folder ? $limit : 200;

            $result = $searchApi->expression($search_expression)
                ->maxResults($max_results)
                ->execute();

            $this->debug->log('Search results received', [
                'total_count' => count($result['resources'] ?? []),
                'expression_used' => $search_expression,
                'folder' => $folder
            ]);

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

                $this->debug->log('Images grouped by category', array_map('count', $images_by_category));

                // Get random images from each category
                $categories = array_keys($images_by_category);
                $images_per_category = ceil($limit / count($categories));
                
                $selected_images = array();
                foreach ($categories as $category) {
                    $category_images = $images_by_category[$category];
                    shuffle($category_images);
                    $selected_images = array_merge(
                        $selected_images,
                        array_slice($category_images, 0, $images_per_category)
                    );
                }

                // Final shuffle and limit
                shuffle($selected_images);
                $resources = array_slice($selected_images, 0, $limit);
            }

            // Apply pagination
            $resources = array_slice($resources, $offset, $limit);

            // Cache the results for 5 minutes
            set_transient($transient_key, $resources, 5 * MINUTE_IN_SECONDS);

            $this->debug->log('Returning search results', [
                'count' => count($resources),
                'folder' => $folder,
                'sort' => $sort
            ]);

            return $resources;
        } catch (Exception $e) {
            $this->debug->log('Cloudinary search error', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ], 'error');
            return [];
        }
    }
} 