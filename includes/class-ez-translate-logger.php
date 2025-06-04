<?php
/**
 * EZ Translate Logger Class
 *
 * Handles logging functionality for the EZ Translate plugin
 *
 * @package EZTranslate
 * @since 1.0.0
 */

namespace EZTranslate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}


/**
 * Logger class for EZ Translate
 *
 * @since 1.0.0
 */
class Logger {

    /**
     * Log levels
     *
     * @var array
     * @since 1.0.0
     */
    const LOG_LEVELS = array(
        'error'   => 1,
        'warning' => 2,
        'info'    => 3,
        'debug'   => 4,
    );

    /**
     * Current log level
     *
     * @var int
     * @since 1.0.0
     */
    private static $log_level = 3; // Default to 'info'

    /**
     * Initialize logger
     *
     * @since 1.0.0
     */
    public static function init() {
        // Set log level based on WP_DEBUG
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::$log_level = 4; // Debug level
        } else {
            self::$log_level = 2; // Warning level for production
        }
    }

    private static function should_log(){
        return !(defined('EZ_TRANSLATE_TESTS') && EZ_TRANSLATE_TESTS);
    }

    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @param array  $context Additional context data
     * @since 1.0.0
     */
    public static function error($message, $context = array()) {
        if (!self::should_log()) {
            return;
        }
        self::log('error', $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @param array  $context Additional context data
     * @since 1.0.0
     */
    public static function warning($message, $context = array()) {
        if (!self::should_log()) {
            return;
        }
        self::log('warning', $message, $context);
    }

    /**
     * Log an info message
     *
     * @param string $message The message to log
     * @param array  $context Additional context data
     * @since 1.0.0
     */
    public static function info($message, $context = array()) {
        if (!self::should_log()) {
            return;
        }
        self::log('info', $message, $context);
    }

    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @param array  $context Additional context data
     * @since 1.0.0
     */
    public static function debug($message, $context = array()) {
        if (!self::should_log()) {
            return;
        }
        self::log('debug', $message, $context);
    }

    /**
     * Log a message
     *
     * @param string $level   Log level
     * @param string $message The message to log
     * @param array  $context Additional context data
     * @since 1.0.0
     */
    private static function log($level, $message, $context = array()) {
        if (!self::should_log()) {
            return;
        }
        
        // Check if we should log this level
        if (!isset(self::LOG_LEVELS[$level]) || self::LOG_LEVELS[$level] > self::$log_level) {
            return;
        }

        // Format the message
        $formatted_message = self::format_message($level, $message, $context);

        // Log to WordPress error log
        error_log($formatted_message);

        // For critical errors, also log to WordPress admin notices
        if ($level === 'error' && is_admin()) {
            self::add_admin_notice($message, 'error');
        }
    }

    /**
     * Format log message
     *
     * @param string $level   Log level
     * @param string $message The message to log
     * @param array  $context Additional context data
     * @return string Formatted message
     * @since 1.0.0
     */
    private static function format_message($level, $message, $context = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = sprintf('[EZ-Translate] [%s] %s: %s', $timestamp, strtoupper($level), $message);

        // Add context if provided
        if (!empty($context)) {
            $formatted .= ' | Context: ' . wp_json_encode($context);
        }

        return $formatted;
    }

    /**
     * Add admin notice for critical errors
     *
     * @param string $message The message to display
     * @param string $type    Notice type (error, warning, success, info)
     * @since 1.0.0
     */
    private static function add_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>EZ Translate:</strong> %s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }

    /**
     * Log database operations
     *
     * @param string $operation The database operation (create, read, update, delete)
     * @param string $table     The table or option name
     * @param mixed  $data      The data involved in the operation
     * @since 1.0.0
     */
    public static function log_db_operation($operation, $table, $data = null) {
        // Database operations logging removed for performance
        // Parameters kept for backward compatibility
    }

    /**
     * Log API requests
     *
     * @param string $endpoint The API endpoint
     * @param string $method   HTTP method
     * @param array  $data     Request data
     * @since 1.0.0
     */
    public static function log_api_request($endpoint, $method, $data = array()) {
        // API request logging removed for performance
        // Parameters kept for backward compatibility
    }

    /**
     * Log validation errors
     *
     * @param string $field  The field that failed validation
     * @param string $value  The invalid value
     * @param string $reason The reason for validation failure
     * @since 1.0.0
     */
    public static function log_validation_error($field, $value, $reason) {
        if (!self::should_log()) {
            return;
        }
        $context = array(
            'field'  => $field,
            'value'  => $value,
            'reason' => $reason,
        );

        self::warning("Validation failed for field: {$field}", $context);
    }

    /**
     * Get current log level
     *
     * @return int Current log level
     * @since 1.0.0
     */
    public static function get_log_level() {
        return self::$log_level;
    }

    /**
     * Set log level
     *
     * @param int $level Log level to set
     * @since 1.0.0
     */
    public static function set_log_level($level) {
        if (in_array($level, self::LOG_LEVELS, true)) {
            self::$log_level = $level;
        }
    }
}

// Initialize logger
Logger::init();
