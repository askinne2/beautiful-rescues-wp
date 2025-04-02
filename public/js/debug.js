/**
 * Debug utility for Beautiful Rescues
 */
const BRDebug = {
    enabled: false,
    logLevel: 'info',
    levels: {
        'error': 0,
        'warning': 1,
        'info': 2,
        'debug': 3
    },

    init() {
        // Get debug state from PHP if available
        if (typeof window.BR_DEBUG !== 'undefined') {
            this.enabled = window.BR_DEBUG.enabled && window.BR_DEBUG.browser_debug;
            this.logLevel = window.BR_DEBUG.log_level;
            this.debug('Debug utility initialized', {
                enabled: this.enabled,
                logLevel: this.logLevel,
                browserDebug: window.BR_DEBUG.browser_debug
            });
        }
    },

    shouldLog(level) {
        return this.enabled && this.levels[level] <= this.levels[this.logLevel];
    },

    log(message, data = null, level = 'info') {
        if (!this.shouldLog(level)) return;

        const timestamp = new Date().toISOString();
        const logMessage = `[${timestamp}] [${level.toUpperCase()}] ${message}`;
        
        // Use appropriate console method based on level
        switch (level) {
            case 'error':
                console.error(logMessage);
                break;
            case 'warning':
                console.warn(logMessage);
                break;
            case 'debug':
                console.debug(logMessage);
                break;
            default:
                console.log(logMessage);
        }

        if (data !== null) {
            // Format the data object for better readability
            const formattedData = typeof data === 'object' 
                ? JSON.stringify(data, null, 2)
                : data;
            
            // Log data with appropriate console method
            switch (level) {
                case 'error':
                    console.error('Data:', formattedData);
                    break;
                case 'warning':
                    console.warn('Data:', formattedData);
                    break;
                case 'debug':
                    console.debug('Data:', formattedData);
                    break;
                default:
                    console.log('Data:', formattedData);
            }
        }
    },

    error(message, data = null) {
        this.log(message, data, 'error');
    },

    warn(message, data = null) {
        this.log(message, data, 'warning');
    },

    info(message, data = null) {
        this.log(message, data, 'info');
    },

    debug(message, data = null) {
        this.log(message, data, 'debug');
    }
};

// Initialize debug utility
BRDebug.init(); 