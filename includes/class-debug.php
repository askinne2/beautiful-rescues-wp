<?php
/**
 * Debug Class
 */
class BR_Debug {
    private static $instance = null;
    private $is_debug_enabled = false;
    private $log_file = 'beautiful-rescues-debug.log';
    private $log_path;
    private $log_levels = array(
        'error' => 0,
        'warning' => 1,
        'info' => 2,
        'debug' => 3
    );
    private $current_log_level = 2; // Default to 'info' level

    private function __construct() {
        // Check for debug mode in environment or plugin options
        $options = get_option('beautiful_rescues_options', array());
        $this->is_debug_enabled = (getenv('BR_DEBUG') === 'true') ||
                                 ($options['enable_debug'] ?? false);

        // Set log level from environment or default to 'info'
        $env_log_level = getenv('BR_LOG_LEVEL');
        if ($env_log_level && isset($this->log_levels[$env_log_level])) {
            $this->current_log_level = $this->log_levels[$env_log_level];
        }

        // Ensure WP_CONTENT_DIR is defined
        if (!defined('WP_CONTENT_DIR')) {
            error_log('Beautiful Rescues Debug: WP_CONTENT_DIR is not defined');
            return;
        }

        $this->log_path = WP_CONTENT_DIR . '/' . $this->log_file;
        
        // Expose debug state to JavaScript if browser debug is enabled
        if ($options['enable_browser_debug'] ?? false) {
            add_action('wp_footer', array($this, 'expose_debug_state'));
        }
    }

    private function test_log_file() {
        try {
            // Log to WordPress debug log first
            error_log('Beautiful Rescues Debug: Starting debug system initialization');
            
            if (!is_writable(WP_CONTENT_DIR)) {
                error_log('Beautiful Rescues Debug: WP_CONTENT_DIR is not writable: ' . WP_CONTENT_DIR);
                return;
            }

            if (file_exists($this->log_path)) {
                if (!is_writable($this->log_path)) {
                    error_log('Beautiful Rescues Debug: Log file exists but is not writable: ' . $this->log_path);
                    return;
                }
            } else {
                if (!is_writable(dirname($this->log_path))) {
                    error_log('Beautiful Rescues Debug: Cannot create log file - directory not writable: ' . dirname($this->log_path));
                    return;
                }
            }

            // Test write
            $test_message = sprintf(
                "[%s] [INFO] Debug system initialized at %s\n",
                current_time('mysql'),
                $this->log_path
            );
            
            if (file_put_contents($this->log_path, $test_message, FILE_APPEND) === false) {
                error_log('Beautiful Rescues Debug: Failed to write to log file: ' . $this->log_path);
                return;
            }

        } catch (Exception $e) {
            error_log('Beautiful Rescues Debug: Error initializing debug system - ' . $e->getMessage());
        }
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($message, $data = null, $type = 'info') {
        if (!$this->is_debug_enabled) {
            return;
        }

        // Check if we should log based on level
        if (!isset($this->log_levels[$type]) || $this->log_levels[$type] > $this->current_log_level) {
            return;
        }

        try {
            $timestamp = current_time('mysql');
            $log_message = sprintf(
                "[%s] [%s] %s\n",
                $timestamp,
                strtoupper($type),
                $message
            );

            if ($data !== null) {
                // Only log data for error and warning levels, or if explicitly requested
                if ($type === 'error' || $type === 'warning' || $this->current_log_level >= 3) {
                    $log_message .= "Data: " . print_r($data, true) . "\n";
                }
            }

            // Write to WordPress debug log first
            error_log('Beautiful Rescues Debug: ' . $log_message);

            // Write to our custom log file
            if (file_put_contents($this->log_path, $log_message, FILE_APPEND) === false) {
                error_log('Beautiful Rescues Debug: Failed to write log message to: ' . $this->log_path);
            }
        } catch (Exception $e) {
            error_log('Beautiful Rescues Debug: Error writing log - ' . $e->getMessage());
        }
    }

    public function is_enabled() {
        return $this->is_debug_enabled;
    }

    public function enable() {
        $this->is_debug_enabled = true;
        $this->log('Debug mode enabled manually', null, 'info');
    }

    public function disable() {
        $this->is_debug_enabled = false;
        $this->log('Debug mode disabled manually', null, 'info');
    }

    public function set_log_level($level) {
        if (isset($this->log_levels[$level])) {
            $this->current_log_level = $this->log_levels[$level];
            $this->log("Log level changed to: $level", null, 'info');
            return true;
        }
        return false;
    }

    public function get_debug_state() {
        return array(
            'enabled' => $this->is_debug_enabled,
            'log_level' => array_search($this->current_log_level, $this->log_levels)
        );
    }

    public function expose_debug_state() {
        if (!is_admin()) {
            $options = get_option('beautiful_rescues_options', array());
            $debug_state = array(
                'enabled' => $this->is_debug_enabled,
                'log_level' => array_search($this->current_log_level, $this->log_levels),
                'browser_debug' => $options['enable_browser_debug'] ?? false
            );
            echo '<script>window.BR_DEBUG = ' . json_encode($debug_state) . ';</script>';
        }
    }
} 