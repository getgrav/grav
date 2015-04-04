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

    // dashboard
    var chart = $('.updates-chart'), UpdatesChart;
    if (chart.length) {
        var data = {
          series: [100, 0]
        };

        var options = {
          donut: true,
          donutWidth: 10,
          startAngle: 0,
          total: 100,
          showLabel: false,
          height: 150
        };

        UpdatesChart = Chartist.Pie('.updates-chart .ct-chart', data, options);
        UpdatesChart.on('draw', function(data){
            if (data.index) { return; }
            chart.find('.numeric span').text(Math.round(data.value) + '%');
        });
    }

    var ajaxRequest = function (url, settings) {
        settings = typeof settings === 'undefined' ? typeof url === 'string' ? {} : url : settings;
        settings.url = typeof settings.url === 'undefined' && typeof url === 'string' ? url : settings.url;

        var successHandler = typeof settings.success !== 'undefined' ? typeof settings.success === 'function' ? [ settings.success ] : settings.success : [];
        successHandler.unshift(ajaxRequest.logoutHandler);
        settings.success = successHandler;

        return $.ajax(settings);
    };
    ajaxRequest.logoutHandler = function (response, status, xhr) {
        if (response.status && (response.status === "unauthorized" || response.status === "forbidden")) {
            document.location.href = GravAdmin.config.base_url_relative;
            throw "Logged out";
        }
    };

    // Cache Clear
    $('[data-clear-cache]').on('click', function(e) {

        $(this).attr('disabled','disabled').find('> .fa').removeClass('fa-trash').addClass('fa-refresh fa-spin');
        var url = $(this).data('clearCache');

        ajaxRequest({
            dataType: "json",
            url: url,
            success: function(result, status) {
                if (result.status == 'success') {
                    toastr.success(result.message);
                } else {
                    toastr.error(result.message);
                }
            }
        }).complete(function() {
            $('[data-clear-cache]').removeAttr('disabled').find('> .fa').removeClass('fa-refresh fa-spin').addClass('fa-trash');
        });
    });

    ajaxRequest({
        dataType: "JSON",
        url: window.location.href,
        method: "POST",
        data: {
            task:   'GPM',
            action: 'getUpdates'
        },
        success: function (response) {
            if (!response.success) {
                throw new Error(response.message);
            }

            var grav = response.payload.grav,
                installed = response.payload.installed,
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

            // dashboard
            if ($('.updates-chart').length) {
                var missing = (resources.total + (grav.isUpdatable ? 1 : 0)) * 100 / (installed + (grav.isUpdatable ? 1 : 0)),
                    updated = 100 - missing;
                UpdatesChart.update({series: [updated, missing]});
            }

            if (resources.total > 0) {
                var length,
                    icon = '<i class="fa fa-bullhorn"></i>',
                    content = '{updates} of your {type} have an <strong>update available</strong>',
                    button = '<button class="button button-small secondary">Update {Type}</button>',
                    plugins = $('.grav-update.plugins'),
                    themes = $('.grav-update.themes'),
                    sidebar = {plugins: $('#admin-menu a[href$="/plugins"]'), themes: $('#admin-menu a[href$="/themes"]')};

                // sidebar
                if (sidebar.plugins.length || sidebar.themes.length) {
                    var length, badges;
                    if (sidebar.plugins.length && (length = Object.keys(resources.plugins).length)) {
                        badges = sidebar.plugins.find('.badges');
                        badges.addClass('with-updates');
                        badges.find('.badge.updates').text(length);
                    }

                    if (sidebar.themes.length && (length = Object.keys(resources.themes).length)) {
                        badges = sidebar.themes.find('.badges');
                        badges.addClass('with-updates');
                        badges.find('.badge.updates').text(length);
                    }
                }

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
        }
    });
});
