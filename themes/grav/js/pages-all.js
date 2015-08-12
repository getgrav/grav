$(function(){
    var root = window || {};
    root = root.GravJS = root.GravJS || {};

    //Make it global because used by ./forms/form.js
    root.currentValues = getState();
    var clickedLink;

    // selectize
    $('input.page-filter').selectize({
        maxItems: null,
        valueField: 'flag',
        labelField: 'flag',
        searchField: ['flag'],
        options: [
            {flag: 'Modular'},
            {flag: 'Visible'},
            {flag: 'Routable'}
        ]
    });


    var childrenToggles = $('[data-toggle="children"]'),
        storage = sessionStorage.getItem('grav:admin:pages'),
        collapseAll = function(store) {
            childrenToggles.each(function(i, element){
                var icon = $(element).find('.page-icon'),
                    open = icon.hasClass('children-open'),
                    key = $(element).closest('[data-nav-id]').data('nav-id'),
                    children = $(element).closest('li.page-item').find('ul:first');

                if (open) {
                    children.hide();
                    if (store) delete storage[key];
                    icon.removeClass('children-open').addClass('children-closed');
                }

                if (store) sessionStorage.setItem('grav:admin:pages', JSON.stringify(storage));
            });
        },
        expandAll = function(store) {
            childrenToggles.each(function(i, element){
                var icon = $(element).find('.page-icon'),
                    open = icon.hasClass('children-open'),
                    key = $(element).closest('[data-nav-id]').data('nav-id'),
                    children = $(element).closest('li.page-item').find('ul:first');

                if (!open) {
                    children.show();
                    if (store) storage[key] = 1;
                    icon.removeClass('children-closed').addClass('children-open');
                }

                if (store) sessionStorage.setItem('grav:admin:pages', JSON.stringify(storage));
            });
        },
        restoreStates = function() {
            collapseAll();
            for (var key in storage) {
                var element = $('[data-nav-id="' + key + '"]'),
                    icon = element.find('.page-icon').first(),
                    open = icon.hasClass('children-open'),
                    children = element.closest('li.page-item').find('ul:first');

                children.show();
                icon.removeClass('children-closed').addClass('children-open');
            }
        };

    if (!storage) {
        sessionStorage.setItem('grav:admin:pages', (storage = '{}'));
    }

    storage = JSON.parse(storage);

    restoreStates();

    var startFilterPages = function () {
        var task = 'task' + GravAdmin.config.param_sep;

        $('input[name="page-search"]').focus();
        var flags = $('input[name="page-filter"]').val(),
            query = $('input[name="page-search"]').val();

        if (!flags.length && !query.length) {
            GravAjax.jqxhr.abort();
            return finishFilterPages([], true);
        }

        GravAjax({
            dataType: 'json',
            method: 'POST',
            url: GravAdmin.config.base_url_relative + '/pages-filter.json/' + task + 'filterPages',
            data: {
                flags: flags,
                query: query
            },
            toastErrors: true,
            success: function (result, status) {
                finishFilterPages(result.results);
            }
        });
    };

    var finishFilterPages = function (pages, reset) {
        var items = $('[data-nav-id]');

        items.removeClass('search-match');

        if (reset) {
            items.addClass('search-match');
            restoreStates();
        } else {
            pages.forEach(function (id) {
                var match = items.filter('[data-nav-id="' + id + '"]'),
                    parents = match.parents('[data-nav-id]');
                match.addClass('search-match');
                match.find('[data-nav-id]').addClass('search-match');
                parents.addClass('search-match');
                parents.find('[data-toggle="children"]').each(function(index, element){
                    var icon = $(this).find('.page-icon'),
                        open = icon.hasClass('children-open'),
                        children = $(this).closest('li.page-item').find('ul:first');

                    if (!open) {
                        children.show();
                        icon.removeClass('children-closed').addClass('children-open');
                    }
                });
            });
        }

        items.each(function (key, item) {
            if ($(item).hasClass('search-match')) {
                $(item).show();
            } else {
                $(item).hide();
            }
        });
    };

    // selectize
    $('input[name="page-search"]').on('input', startFilterPages);
    $('input[name="page-filter"]').on('change', startFilterPages);


    // auto generate folder based on title
    // on user input on folder, autogeneration stops
    // if user empties the folder, autogeneration restarts
    $('input[name="folder"]').on('input', function(){
        $(this).data('user-custom-folder', true);
        if (!$(this).val()) $(this).data('user-custom-folder', false);
    });

    $('input[name="title"]').on('input', function(e){
        if (!$('input[name="folder"]').data('user-custom-folder')) {
            folder = $(this).val().toLowerCase().replace(/\s/g, '-').replace(/[^a-z0-9_\-]/g, '');
            $('input[name="folder"]').val(folder);
        }
    });

    $('input[name="folder"]').on('input', function(e){
        value = $(this).val().toLowerCase().replace(/\s/g, '-').replace(/[^a-z0-9_\-]/g, '');
        $(this).val(value);
    });

    childrenToggles.on('click', function () {
        var icon = $(this).find('.page-icon'),
            open = icon.hasClass('children-open'),
            key = $(this).closest('[data-nav-id]').data('nav-id'),
            children = $(this).closest('li.page-item').find('ul:first');

        if (open) {
            children.hide();
            delete storage[key];
            icon.removeClass('children-open').addClass('children-closed');
        } else {
            children.show();
            storage[key] = true;
            icon.removeClass('children-closed').addClass('children-open');
        }

        sessionStorage.setItem('grav:admin:pages', JSON.stringify(storage));
    });

    $('[data-page-toggleall]').on('click', function() {
        var state = $(this).data('page-toggleall');
        if (state == 'collapse') collapseAll(true);
        else expandAll(true);
    });

    $('#admin-main button').on('click', function(){
        $(window).off('beforeunload');
    });

    $('[data-remodal-id] form').on('submit', function(){
        $(window).off('beforeunload');
    });

    $("#admin-mode-toggle input[name=mode-switch]").on('change', function(e){
        var value = $(this).val(),
            uri   = $(this).data('leave-url');

        if (root.currentValues == getState()) {
            setTimeout(function(){
                window.location.href = uri;
            }, 200)

            return true;
        }

        e.preventDefault();

        var confirm = $.remodal.lookup[$('[data-remodal-id=changes]').data('remodal')],
            buttons = $('[data-remodal-id=changes] a.button'),
            action;

        buttons.on('click', function(e){
            e.preventDefault();
            action = $(this).data('leave-action');

            buttons.off('click');
            confirm.close();

            if (action == 'continue') {
                $(window).off('beforeunload');
                window.location.href = $("#admin-mode-toggle input[name=mode-switch]:checked").data('leave-url');
            } else {
                $('input[name=mode-switch][checked]').prop('checked', true);
            }
        });

        confirm.open();
    });

    $('a[href]:not([href^=#])').on('click', function(e){
        if (root.currentValues != getState()){
            e.preventDefault();

            clickedLink = $(this).attr('href');

            var confirm = $.remodal.lookup[$('[data-remodal-id=changes]').data('remodal')],
                buttons = $('[data-remodal-id=changes] a.button'),
                action;

            buttons.on('click', function(e){
                e.preventDefault();
                action = $(this).data('leave-action');

                buttons.off('click');
                confirm.close();

                if (action == 'continue') {
                    $(window).off('beforeunload');
                    window.location.href = clickedLink;
                }
            });

            confirm.open();
        }
    });

    // deletion
    $('[data-remodal-target="delete"]').on('click', function(){
        var okdelete = $('[data-remodal-id=delete] a.button');

        okdelete.data('delete-action', $(this).data('delete-url'));
    });

    $('[data-delete-action]').on('click', function(){
        var confirm  = $.remodal.lookup[$('[data-remodal-id=delete]').data('remodal')],
            okdelete = $(this).data('delete-action');

        window.location.href = okdelete;
        confirm.close();
    });

    $(window).on('beforeunload', function(){
        if (root.currentValues != getState()){
            return "You have made changes on this page that you have not yet confirmed. If you navigate away from this page you will lose your unsaved changes";
        }
    });

    // Move dropdown sync (on dropdown change)
    /*$('body').on('change', '[data-page-move] select', function(){
        var route = jQuery('form#blueprints').first().find('select[name="route"]'),
            value = $(this).val();
        if (route.length && route.val() !== value) {
            route.val(value);
            route.data('selectize').setValue(value);
        }
    });*/

    // Move dropdown sync (on continue)
    $('[data-page-move] button').on('click', function(){
        var route = jQuery('form#blueprints').first().find('select[name="route"]'),
            value = $('[data-page-move] select').val();
        if (route.length && route.val() !== value) {
            route.val(value);
            route.data('selectize').setValue(value);
        }
    });
});
