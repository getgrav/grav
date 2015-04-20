$(function(){
    var root = window || {};

    root.GravAjax = function (url, settings) {
        settings = typeof settings === 'undefined' ? typeof url === 'string' ? {} : url : settings;
        settings.url = typeof settings.url === 'undefined' && typeof url === 'string' ? url : settings.url;

        var successHandler = typeof settings.success !== 'undefined' ? typeof settings.success === 'function' ? [ settings.success ] : settings.success : [];
        successHandler.unshift(root.GravAjax.logoutHandler);
        settings.success = successHandler;

        return $.ajax(settings);
    };
    root.GravAjax.logoutHandler = function (response, status, xhr) {
        if (response.status && (response.status === "unauthorized" || response.status === "forbidden")) {
            document.location.href = GravAdmin.config.base_url_relative;
            throw "Logged out";
        }
    };
});
