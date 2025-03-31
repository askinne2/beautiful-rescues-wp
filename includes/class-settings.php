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
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Beautiful Rescues',
            'Beautiful Rescues',
            'manage_options',
            'beautiful-rescues',
            array($this, 'render_settings_page'),
            'dashicons-heart',
            30
        );

        add_submenu_page(
            'beautiful-rescues',
            'Settings',
            'Settings',
            'manage_options',
            'beautiful-rescues',
            array($this, 'render_settings_page')
        );
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
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('beautiful_rescues_options');
                do_settings_sections('beautiful-rescues');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function render_cloudinary_section() {
        echo '<p>Enter your Cloudinary credentials below:</p>';
    }

    public function render_debug_section() {
        echo '<p>Enable debug mode to log detailed information about plugin operations:</p>';
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
            Enable debug logging
        </label>
        <p class="description">When enabled, detailed logs will be written to <?php echo esc_html(WP_CONTENT_DIR . '/beautiful-rescues-debug.log'); ?></p>
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
        $value = get_option('watermark_url', 'https://res.cloudinary.com/dgnb4yyrc/image/upload/v1743356531/br-watermark-2025_2x_baljip.webp');
        ?>
        <input type="text" name="watermark_url" 
               value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Enter the full URL of your watermark image. Recommended format: WebP with transparency.</p>
        <?php
    }
} 