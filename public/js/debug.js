/**
 * Debug utility for Beautiful Rescues
 * 
 * This utility provides conditional logging based on debug settings.
 * It ensures that console logs are only output when debug mode is enabled.
 */

(function($) {
    'use strict';

    // Create a global debug object if it doesn't exist
    window.beautifulRescuesDebug = window.beautifulRescuesDebug || {};

    // Initialize debug settings
    beautifulRescuesDebug.init = function(settings) {
        beautifulRescuesDebug.enabled = settings.enabled || false;
        beautifulRescuesDebug.browserEnabled = settings.browserEnabled || false;
        
        // Log initialization if browser debug is enabled
        if (beautifulRescuesDebug.browserEnabled) {
            console.log('Beautiful Rescues Debug: Initialized', {
                serverDebug: beautifulRescuesDebug.enabled,
                browserDebug: beautifulRescuesDebug.browserEnabled
            });
        }
    };

    // Log function that only logs if browser debug is enabled
    beautifulRescuesDebug.log = function(message, data) {
        if (beautifulRescuesDebug.browserEnabled) {
            if (data) {
                console.log('Beautiful Rescues: ' + message, data);
            } else {
                console.log('Beautiful Rescues: ' + message);
            }
        }
    };

    // Error function that always logs errors regardless of debug settings
    beautifulRescuesDebug.error = function(message, data) {
        if (data) {
            console.error('Beautiful Rescues Error: ' + message, data);
        } else {
            console.error('Beautiful Rescues Error: ' + message);
        }
    };

    // Warning function that logs if browser debug is enabled
    beautifulRescuesDebug.warn = function(message, data) {
        if (beautifulRescuesDebug.browserEnabled) {
            if (data) {
                console.warn('Beautiful Rescues Warning: ' + message, data);
            } else {
                console.warn('Beautiful Rescues Warning: ' + message);
            }
        }
    };

    // Info function that logs if browser debug is enabled
    beautifulRescuesDebug.info = function(message, data) {
        if (beautifulRescuesDebug.browserEnabled) {
            if (data) {
                console.info('Beautiful Rescues Info: ' + message, data);
            } else {
                console.info('Beautiful Rescues Info: ' + message);
            }
        }
    };

    // Group function that logs if browser debug is enabled
    beautifulRescuesDebug.group = function(message) {
        if (beautifulRescuesDebug.browserEnabled) {
            console.group('Beautiful Rescues: ' + message);
        }
    };

    // GroupEnd function that logs if browser debug is enabled
    beautifulRescuesDebug.groupEnd = function() {
        if (beautifulRescuesDebug.browserEnabled) {
            console.groupEnd();
        }
    };

})(jQuery); 