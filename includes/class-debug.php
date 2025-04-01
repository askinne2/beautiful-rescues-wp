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
        // Check for debug mode in wp-config.php, environment, or plugin options
        $this->is_debug_enabled = (defined('WP_DEBUG') && WP_DEBUG) || 
                                 (getenv('BR_DEBUG') === 'true') ||
                                 (get_option('beautiful_rescues_options')['enable_debug'] ?? false);

        // Set log level from environment or default to 'info'
        $env_log_level = getenv('BR_LOG_LEVEL');
        if ($env_log_level && isset($this->log_levels[$env_log_level])) {
            $this->current_log_level = $this->log_levels[$env_log_level];
        }

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
} 