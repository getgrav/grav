$(function () {
    jQuery.substitute = function(str, sub) {
        return str.replace(/\{(.+?)\}/g, function($0, $1) {
            return $1 in sub ? sub[$1] : $0;
        });
    };

    // selectize
    $('select.fancy').selectize({
        createOnBlur: true
    });

    $('input.fancy').selectize({
        delimiter: ',',
        persist:   false,
        create:    function (input) {
            return {
                value: input,
                text:  input
            }
        }
    });

    // Set Toastr defaults
    toastr.options = {
        "positionClass": "toast-top-right"
    }

    // Cache Clear
    $('[data-clear-cache]').on('click', function(e) {

        $(this).attr('disabled','disabled').find('> .fa').removeClass('fa-trash').addClass('fa-refresh fa-spin');
        var url = $(this).data('clearCache');
        var jqxhr = $.getJSON(url, function(result, status) {
            if (result.status == 'success') {
                toastr.success(result.message);
            } else {
                toastr.error(result.message);
            }
        });
        jqxhr.complete(function() {
            $('[data-clear-cache]').removeAttr('disabled').find('> .fa').removeClass('fa-refresh fa-spin').addClass('fa-trash');
        });
    });

    // GPM
    $.post(window.location.href, {
        task:   'GPM',
        action: 'getUpdates'
    }, function (response) {
        if (!response.success) {
            throw new Error(response.message);
        }

        var grav = response.payload.grav,
            resources = response.payload.resources;

        //console.log(grav, resources);

        // grav updatable
        if (grav.isUpdatable) {
            var icon    = '<i class="fa fa-bullhorn"></i> ';
                content = 'Grav <b>v{available}</b> is now available! <span class="less">(Current: v{version})</span> ',
                button  = '<button class="button button-small secondary" data-gpm-update="grav">Update Grav Now</button>';

            content = jQuery.substitute(content, {available: grav.available, version: grav.version});
            $('[data-gpm-grav]').addClass('grav').html('<p>' + icon + content + button + '</p>');
        }

        if (resources.total > 0) {
            var length,
                icon = '<i class="fa fa-bullhorn"></i>',
                content = '{updates} of your {type} have an <strong>update available</strong>',
                button = '<button class="button button-small secondary">Update {Type}</button>',
                plugins = $('.grav-update.plugins'),
                themes = $('.grav-update.themes');

            // list page
            if (plugins[0] && (length = Object.keys(resources.plugins).length)) {
                content = jQuery.substitute(content, {updates: length, type: 'plugins'});
                button = jQuery.substitute(button, {Type: 'All Plugins'});
                plugins.html('<p>' + icon + content + button + '</p>');

                var plugin, url;
                $.each(resources.plugins, function (key, value) {
                    plugin = $('[data-gpm-plugin="' + key + '"] .gpm-name');
                    url = plugin.find('a');
                    plugin.append('<a href="' + url.attr('href') + '"><span class="badge update">Update available!</span></a>');

                });
            }

            if (themes[0] && (length = Object.keys(resources.themes).length)) {
                content = jQuery.substitute(content, {updates: length, type: 'themes'});
                button = jQuery.substitute(button, {Type: 'All Themes'});
                themes.html('<p>' + icon + content + button + '</p>');

                var theme, url;
                $.each(resources.themes, function (key, value) {
                    theme = $('[data-gpm-theme="' + key + '"]');
                    url = theme.find('.gpm-name a');
                    theme.append('<div class="gpm-ribbon"><a href="' + url.attr('href') + '">UPDATE</a></div>');
                });
            }

            // details page
            var type = 'plugin',
                details = $('.grav-update.plugin')[0];

            if (!details) {
                details = $('.grav-update.theme')[0];
                type = 'theme';
            }

            if (details){
                var slug = $('[data-gpm-' + type + ']').data('gpm-' + type),
                    Type = type.charAt(0).toUpperCase() + type.substring(1);

                content = '<strong>v{available}</strong> of this ' + type + ' is now available!';
                content = jQuery.substitute(content, {available: resources[type + 's'][slug].available});
                button  = jQuery.substitute(button, {Type: Type});
                $(details).html('<p>' + icon + content + button + '</p>');
            }
        }

    }, 'json');
});
