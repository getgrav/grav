$(document).ready(function(){
    $('input[data-grav-field-datetime]').each(function() {
        var $input = $(this),
            min = $input.attr('min'),
            max = $input.attr('max'),
            regex, match,
            kendoOptions = { format: "dd-MM-yyyy HH:mm", timeFormat: "HH:mm" };

        if (min || max) {
            regex = /(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2})/;
        }

        if (min && (match = regex.exec(min))) {
            kendoOptions.min = new Date(
                (+match[3]),
                (+match[2])-1,
                (+match[1]),
                (+match[4]),
                (+match[5])
            );
        }

        if (max && (match = regex.exec(max))) {
            kendoOptions.max = new Date(
                (+match[3]),
                (+match[2])-1,
                (+match[1]),
                (+match[4]),
                (+match[5])
            );
        }

        $input.kendoDateTimePicker(kendoOptions);

        // Reset when user manually types in invalid date
        $input.on('change', function () {
            $input.css('opacity', 1);
            $input.parents('.form-data').css('opacity', 1);
            var kWidget = $input.data('kendoDateTimePicker');
            if (kWidget && kWidget.value() === null && $input.val()) {
                kWidget.value($input.data('kendo-previous') || "");
            } else {
                $input.data('kendo-previous', kWidget.value() || "");
            }
        });
    });
});
