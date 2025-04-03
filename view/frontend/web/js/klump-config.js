define([], function() {
    'use strict';
    return {
        scriptUrl: 'https://js.useklump.com/klump.js',
        loadScript: function(callback) {
            if (typeof Klump === 'undefined') {
                var script = document.createElement('script');
                script.src = this.scriptUrl;
                script.onload = function() {
                    console.log('Klump script loaded successfully.');
                    if (callback) callback();
                };
                script.onerror = function(error) {
                    console.error('Failed to load Klump script.', error);
                };
                document.head.appendChild(script);
            } else if (callback) {
                callback();
            }
        }
    };
});
