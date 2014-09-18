var getState = function(){
    var loadValues = [];
    $('input, select, textarea').each(function(index, element){
        var name  = $(element).prop('name'),
            value = $(element).val();

        if (name)  loadValues.push(name + '|' + value);
    });

    return loadValues.toString();
};

$(function(){

    // selectize
    $('input.page-filter').selectize({
        delimiter: ',',
        create: false
    });

    // auto generate folder based on title
    // on user input on folder, autogeneration stops
    // if user empties the folder, autogeneration restarts
    $('input[name="folder"]').on('input', function(){
        $(this).data('user-custom-folder', true);
        if (!$(this).val()) $(this).data('user-custom-folder', false);
    })
    $('input[name="title"]').on('input', function(e){
        if (!$('input[name="folder"]').data('user-custom-folder')) {
            folder = $(this).val().toLowerCase().replace(/\s/g, '-');
            $('input[name="folder"]').val(folder);
        }
    });

    var currentValues = getState(),
        clickedLink;

    $('#admin-main button').on('click', function(){
        $(window).off('beforeunload');
    });

    $("#admin-mode-toggle input[name=mode-switch]").on('change', function(e){
        var value = $(this).val();

        if (currentValues == getState()) {
            setTimeout(function(){
                window.location.href = '{{ uri.route(true) }}' + ((value == 'expert') ? '/expert:1' : '');
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
                window.location.href = '{{ uri.route(true) }}' + ((value == 'expert') ? '/expert:1' : '');
            } else {
                $('input[name=mode-switch][checked]').prop('checked', true);
            }
        });

        confirm.open();
    });

    $('a[href]:not([href^=#])').on('click', function(e){
        if (currentValues != getState()){
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

    $(window).on('beforeunload', function(){
        if (currentValues != getState()){
            return "You have made changes on this page that you have not yet confirmed. If you navigate away from this page you will lose your unsaved changes";
        }
    });
});
