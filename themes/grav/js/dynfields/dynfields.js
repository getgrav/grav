(function($){

    var DynFields = {
        init: function () {
            var container = $('[data-grav-dynfields]');
            DynFields.container = container;
            container.on('click', '[data-grav-addfield]', DynFields.addField.bind(DynFields));
            container.on('click', '[data-grav-remfield]', DynFields.remField.bind(DynFields));
            container.on('keyup', 'input:not([name])', DynFields.updateFields.bind(DynFields));
        },
        addField: function (event, element) {
            element = $(event.target);
            var div = $('<div class="form-row" />').html(this.layout());
            var div = $('<div />').html(this.layout());
            div.insertAfter(element.parent('div'));
        },
        remField: function (event, element) {
            element = $(event.target);
            element.parent('div').remove();
        },
        updateFields: function (event, element) {
            element = $(event.target);
            var sibling = element.next();
            sibling.attr('name', this.getName() + '[' + element.val() + ']');
        },
        getName: function () {
            return this.container.data('grav-dynfields') || 'generic';
        },
        layout: function () {
            var name = this.getName(),
                placeholder = {
                    key: this.container.data('grav-dynfields-key') || 'Key',
                    val: this.container.data('grav-dynfields-value') || 'Value'
                };
            return '' + '   <input type="text" value=""  placeholder="' + placeholder.key + '" />' + '   <input type="text" name="' + name + '[]" value="" placeholder="' + placeholder.val + '" />' + '   <span data-grav-remfield class="button fa fa-minus"></span>   <span data-grav-addfield class="button fa fa-plus"></span>' + '';
        }
    };

    $(DynFields.init);

})(jQuery);
