$(function(){
    var root = window || {};

    root.GravAjax = function (url, settings) {
        settings = typeof settings === 'undefined' ? typeof url === 'string' ? {} : url : settings;
        settings.url = typeof settings.url === 'undefined' && typeof url === 'string' ? url : settings.url;

        var callbacks = {
            success: typeof settings.success !== 'undefined' ? typeof settings.success === 'function' ? [ settings.success ] : settings.success : [],
            error: typeof settings.error !== 'undefined' ? typeof settings.error === 'function' ? [ settings.error ] : settings.error : []
        };

        if (settings.toastErrors) {
            callbacks.error.push(root.GravAjax.toastErrorHandler);
            delete settings.toastErrors;
        }

        delete settings.success;
        delete settings.error;

        var deferred = $.Deferred(),
            jqxhr = $.ajax(settings);

        jqxhr.done(function (response, status, xhr) {
            var responseObject = {
                response: response,
                status: status,
                xhr: xhr
            };

            switch (response.status) {
                case "unauthenticated":
                    document.location.href = GravAdmin.config.base_url_relative;
                    throw "Logged out";
                    break;
                case "unauthorized":
                    responseObject.response.message = responseObject.response.message || "Unauthorized.";
                    root.GravAjax.errorHandler(deferred, callbacks, responseObject);
                    break;
                case "error":
                    responseObject.response.message = responseObject.response.message || "Unknown error.";
                    root.GravAjax.errorHandler(deferred, callbacks, responseObject);
                    break;
                case "success":
                    root.GravAjax.successHandler(deferred, callbacks, responseObject);
                    break;
                default:
                    responseObject.response.message = responseObject.response.message || "Invalid AJAX response.";
                    root.GravAjax.errorHandler(deferred, callbacks, responseObject);
                    break;
            }
        });

        jqxhr.fail(function (xhr, status, error) {
            var response = {
                status: 'error',
                message: error
            };

            root.GravAjax.errorHandler(deferred, callbacks, { xhr: xhr, status: status, response: response});
        });

        root.GravAjax.jqxhr = jqxhr;

        return deferred;

    };

    root.GravAjax.successHandler = function (promise, callbacks, response) {
        callbacks = callbacks.success;
        for (var i = 0; i < callbacks.length; i++) {
            if (typeof callbacks[i] === 'function') {
                callbacks[i](response.response, response.status, response.xhr);
            }
        }

        promise.resolve(response.response, response.status, response.xhr);
    };

    root.GravAjax.errorHandler = function (promise, callbacks, response) {
        callbacks = callbacks.error;
        for (var i = 0; i < callbacks.length; i++) {
            if (typeof callbacks[i] === 'function') {
                callbacks[i](response.xhr, response.status, response.response.message);
            }
        }

        promise.reject(response.xhr, response.status, response.response.message);
    };

    root.GravAjax.toastErrorHandler = function (xhr, status, error) {
        if (status !== 'abort') {
            toastr.error(error);
        }
    };
});
