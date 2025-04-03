<?php
/**
 * Settings Class
 */
class BR_Settings {
    private $options;
    private $debug;

    public function __construct() {
        $this->debug = BR_Debug::get_instance();
        $this->options = get_option('beautiful_rescues_options', array());
        
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting('beautiful_rescues_options', 'beautiful_rescues_options');
        register_setting('beautiful_rescues_options', 'enable_watermark');
        register_setting('beautiful_rescues_options', 'watermark_url');

        add_settings_section(
            'cloudinary_settings',
            'Cloudinary Settings',
            array($this, 'render_cloudinary_section'),
            'beautiful-rescues'
        );

        add_settings_field(
            'enable_watermark',
            'Enable Watermark',
            array($this, 'render_enable_watermark_field'),
            'beautiful-rescues',
            'cloudinary_settings'
        );

        add_settings_field(
            'watermark_url',
            'Watermark URL',
            array($this, 'render_watermark_url_field'),
            'beautiful-rescues',
            'cloudinary_settings'
        );

        add_settings_field(
            'cloudinary_cloud_name',
            'Cloud Name',
            array($this, 'render_cloudinary_cloud_name_field'),
            'beautiful-rescues',
            'cloudinary_settings'
        );

        add_settings_field(
            'cloudinary_api_key',
            'API Key',
            array($this, 'render_cloudinary_api_key_field'),
            'beautiful-rescues',
            'cloudinary_settings'
        );

        add_settings_field(
            'cloudinary_api_secret',
            'API Secret',
            array($this, 'render_cloudinary_api_secret_field'),
            'beautiful-rescues',
            'cloudinary_settings'
        );

        add_settings_field(
            'cloudinary_folder',
            'Default Folder',
            array($this, 'render_cloudinary_folder_field'),
            'beautiful-rescues',
            'cloudinary_settings'
        );

        add_settings_section(
            'email_settings',
            'Email Settings',
            array($this, 'render_email_section'),
            'beautiful-rescues'
        );

        add_settings_field(
            'verification_email',
            'Verification Email Address',
            array($this, 'render_verification_email_field'),
            'beautiful-rescues',
            'email_settings'
        );

        add_settings_section(
            'debug_settings',
            'Debug Settings',
            array($this, 'render_debug_section'),
            'beautiful-rescues'
        );

        add_settings_field(
            'enable_debug',
            'Enable Debug Mode',
            array($this, 'render_enable_debug_field'),
            'beautiful-rescues',
            'debug_settings'
        );

        add_settings_field(
            'enable_browser_debug',
            'Enable Browser Console Logging',
            array($this, 'render_enable_browser_debug_field'),
            'beautiful-rescues',
            'debug_settings'
        );

        // Add maintenance section
        add_settings_section(
            'maintenance_settings',
            'Maintenance Settings',
            array($this, 'render_maintenance_section'),
            'beautiful-rescues'
        );

        add_settings_field(
            'clear_gallery_transients',
            'Clear Gallery Transients',
            array($this, 'render_clear_gallery_transients_field'),
            'beautiful-rescues',
            'maintenance_settings'
        );
    }

    /**
     * Render settings form without wrap div
     */
    public function render_settings_form() {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('beautiful_rescues_options');
            do_settings_sections('beautiful-rescues');
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Render complete settings page with wrap div
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php $this->render_settings_form(); ?>
        </div>
        <?php
    }

    public function render_cloudinary_section() {
        echo '<p>Enter your Cloudinary credentials below:</p>';
    }

    public function render_debug_section() {
        echo '<p>Enable debug mode to log detailed information about plugin operations:</p>';
    }

    public function render_email_section() {
        echo '<p>Configure email settings for verification notifications:</p>';
    }

    public function render_cloudinary_cloud_name_field() {
        $value = $this->options['cloudinary_cloud_name'] ?? '';
        ?>
        <input type="text" name="beautiful_rescues_options[cloudinary_cloud_name]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_cloudinary_api_key_field() {
        $value = $this->options['cloudinary_api_key'] ?? '';
        ?>
        <input type="text" name="beautiful_rescues_options[cloudinary_api_key]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_cloudinary_api_secret_field() {
        $value = $this->options['cloudinary_api_secret'] ?? '';
        ?>
        <input type="password" name="beautiful_rescues_options[cloudinary_api_secret]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_cloudinary_folder_field() {
        $value = $this->options['cloudinary_folder'] ?? 'Cats';
        ?>
        <input type="text" name="beautiful_rescues_options[cloudinary_folder]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_enable_debug_field() {
        $value = $this->options['enable_debug'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="beautiful_rescues_options[enable_debug]" 
                   value="1" <?php checked($value, true); ?>>
            Enable server-side debug logging
        </label>
        <p class="description">When enabled, detailed logs will be written to <?php echo esc_html(WP_CONTENT_DIR . '/beautiful-rescues-debug.log'); ?></p>
        <?php
    }

    public function render_enable_browser_debug_field() {
        $value = $this->options['enable_browser_debug'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="beautiful_rescues_options[enable_browser_debug]" 
                   value="1" <?php checked($value, true); ?>>
            Enable browser console logging
        </label>
        <p class="description">When enabled, debug messages will be logged to the browser's console.</p>
        <?php
    }

    public function render_verification_email_field() {
        $value = $this->options['verification_email'] ?? get_option('admin_email');
        ?>
        <input type="email" name="beautiful_rescues_options[verification_email]" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Email address where verification notifications will be sent.</p>
        <?php
    }

    public function render_enable_watermark_field() {
        $value = get_option('enable_watermark', true);
        ?>
        <label>
            <input type="checkbox" name="enable_watermark" 
                   value="1" <?php checked($value, true); ?>>
            Enable watermark on gallery images
        </label>
        <p class="description">When enabled, a watermark will be added to all gallery images.</p>
        <?php
    }

    public function render_watermark_url_field() {
        $value = get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356913/br-watermark-2025_2x_uux1x2.webp');
        ?>
        <input type="text" name="watermark_url" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Enter the full URL of your watermark image. Recommended format: WebP with transparency.</p>
        <?php
    }

    public function render_maintenance_section() {
        echo '<p>Maintenance tools for the Beautiful Rescues plugin:</p>';
    }

    public function render_clear_gallery_transients_field() {
        ?>
        <button type="button" id="clear-gallery-transients" class="button button-secondary">
            <?php _e('Clear Gallery Transients', 'beautiful-rescues'); ?>
        </button>
        <p class="description">Clear all cached gallery data. This will force the plugin to fetch fresh data from Cloudinary.</p>
        <div id="clear-transients-result" style="margin-top: 10px; display: none;"></div>
        <script>
            jQuery(document).ready(function($) {
                $('#clear-gallery-transients').on('click', function() {
                    var $button = $(this);
                    var $result = $('#clear-transients-result');
                    
                    $button.prop('disabled', true).text('<?php _e('Clearing...', 'beautiful-rescues'); ?>');
                    $result.hide();
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'br_clear_gallery_transients',
                            nonce: '<?php echo wp_create_nonce('br_clear_gallery_transients'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            } else {
                                $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            }
                            $result.show();
                        },
                        error: function() {
                            $result.html('<div class="notice notice-error"><p><?php _e('An error occurred while clearing transients.', 'beautiful-rescues'); ?></p></div>');
                            $result.show();
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('<?php _e('Clear Gallery Transients', 'beautiful-rescues'); ?>');
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Create default settings
     */
    public function create_default_settings() {
        $debug = BR_Debug::get_instance();
        $debug->log('Creating default settings', null, 'info');

        // Set default options
        $default_options = array(
            'cloudinary_folder' => 'Cats',
            'allowed_admin_domains' => 'replit.com,21adsmedia.com',
            'max_file_size' => 5
        );

        // Only update if options don't exist
        if (!get_option('beautiful_rescues_options')) {
            add_option('beautiful_rescues_options', $default_options);
            $debug->log('Default settings created', array(
                'options' => $default_options
            ), 'info');
        } else {
            $debug->log('Settings already exist, skipping default creation', null, 'info');
        }
    }
} 