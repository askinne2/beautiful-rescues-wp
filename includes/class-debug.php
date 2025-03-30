<?php
/**
 * Debug Class
 */
class BR_Debug {
    private static $instance = null;
    private $is_debug_enabled = false;
    private $log_file = 'beautiful-rescues-debug.log';
    private $log_path;

    private function __construct() {
        // Check for debug mode in wp-config.php, environment, or plugin options
        $this->is_debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || 
                                 (getenv('BR_DEBUG') === 'true') ||
                                 (get_option('beautiful_rescues_options')['enable_debug'] ?? false);

        $this->log_path = WP_CONTENT_DIR . '/' . $this->log_file;
        

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
                "[%s] [INFO] Debug system initialized\n",
                current_time('mysql')
            );
            
            if (error_log($test_message, 3, $this->log_path) === false) {
                error_log('Beautiful Rescues Debug: Failed to write to log file: ' . $this->log_path);
                return;
            }

            error_log('Beautiful Rescues Debug: Debug system initialized successfully at: ' . $this->log_path);
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

        try {
            $timestamp = current_time('mysql');
            $log_message = sprintf(
                "[%s] [%s] %s\n",
                $timestamp,
                strtoupper($type),
                $message
            );

            if ($data !== null) {
                $log_message .= "Data: " . print_r($data, true) . "\n";
            }

            // Log to both WordPress debug log and our custom log file
            error_log($log_message);
            if (error_log($log_message, 3, $this->log_path) === false) {
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
        $this->log('Debug mode enabled manually');
    }

    public function disable() {
        $this->is_debug_enabled = false;
        $this->log('Debug mode disabled manually');
    }
} 