/**
 * Tuna Payment Library Loader
 * Intercepta o define() do tuna.js para expor globalmente como window.Tuna
 */
(function() {
    'use strict';
    
    // Save original define if it exists (RequireJS)
    var hasRequireJS = typeof define !== 'undefined' && define.amd;
    var originalDefine = hasRequireJS ? define : null;
    var tunaModule = null;
    
    // Temporarily override define to capture Tuna export
    window.define = function() {
        var name = arguments[0];
        var deps = arguments[1];
        var factory = arguments[2];
        
        // tuna.js calls: define("Tuna", [], factory)
        if (name === "Tuna" || (typeof name === 'string' && name.toLowerCase().includes('tuna'))) {
            // factory é sempre uma função em UMD
            if (typeof factory === 'function') {
                tunaModule = factory();
            } else {
                tunaModule = factory;
            }
            
            window.Tuna = tunaModule;  // Export to global
        }
        
        // Call original define if it exists
        if (originalDefine) {
            return originalDefine.apply(window, arguments);
        }
    };
    
    // Preserve AMD flag
    if (hasRequireJS) {
        window.define.amd = originalDefine.amd;
    }
    
    // Get current script directory for relative URL
    var scripts = document.getElementsByTagName('script');
    var currentScript = scripts[scripts.length - 1];
    var currentDir = currentScript.src.substring(0, currentScript.src.lastIndexOf('/') + 1);
    
    // Construct path to tuna.js
    var tunaPath = currentDir + 'tuna.js';
    
    // Load tuna.js
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = tunaPath;
    script.async = false;
    script.defer = false;
    
    script.onload = function() {
        // Restore original define
        if (originalDefine) {
            window.define = originalDefine;
        } else {
            delete window.define;
        }
        
        // Double-check Tuna is available
        if (typeof window.Tuna === 'function') {
            return;
        } else if (tunaModule && typeof tunaModule === 'function') {
            window.Tuna = tunaModule;
        }
    };
    
    script.onerror = function() {
        if (originalDefine) {
            window.define = originalDefine;
        }
    };
    
    // Insert at start of head
    if (document.head && document.head.firstChild) {
        document.head.insertBefore(script, document.head.firstChild);
    } else if (document.head) {
        document.head.appendChild(script);
    } else {
        document.body.insertBefore(script, document.body.firstChild);
    }
})();
