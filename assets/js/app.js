
(function() {
    const basePath = (typeof window.civentralBasePath !== 'undefined') ? window.civentralBasePath : '../';
    
    // Global loader 
    window.loadCiventralScript = function(src, callback = null) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = basePath + src + '?v=' + Date.now();
            script.async = false; 
            script.onload = () => {
                if (callback) callback();
                resolve();
            };
            script.onerror = (err) => {
                reject(err);
            };
            document.body.appendChild(script);
        });
    };

    // LOAD MODULE
    window.loadCiventralScript('assets/js/header/app.js');
    window.loadCiventralScript('assets/js/department/app.js');
    window.loadCiventralScript('assets/js/profile/app.js');
})();
