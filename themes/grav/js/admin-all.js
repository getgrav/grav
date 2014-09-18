$(function()
{
    // selectize
    $('select.fancy').selectize({
        createOnBlur: true
    });

    $('input.fancy').selectize({
        delimiter: ',',
        persist: false,
        create: function(input) {
            return {
                value: input,
                text: input
            }
        }
    });
});
