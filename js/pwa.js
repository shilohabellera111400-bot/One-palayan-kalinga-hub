if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        var isSub = window.location.pathname.includes('/staff/') || window.location.pathname.includes('/admin/');
        var swUrl = isSub ? '../sw.js' : 'sw.js';
        navigator.serviceWorker.register(swUrl, { scope: isSub ? '../' : './' }).catch(function () {});
    });
}
