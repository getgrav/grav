(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};
    root = root.FormFields = root.FormFields || {};

    var SelectizeField = function (el, form) {
        el = $(el);

        var parent = el.is('[' + form.fieldIndicator + ']') ? el : el.closest('[' + form.fieldIndicator + ']'),
            tagName = parent.data('grav-field').toLowerCase() === 'select' ? 'SELECT' : 'INPUT',
            input = parent.prop('tagName').toUpperCase() === tagName ? parent : parent.find(tagName),
            type = parent.data(form.fieldIndicator);

        input.selectize(parent.data('grav-selectize'));

        this.el = parent;
        this.input = input;
        this.selectize = input[0].selectize;
    };

    SelectizeField.getName = function () {
        return 'selectize';
    };

    SelectizeField.getTypes = function () {
        return [ 'selectize', 'select'];
    };

    SelectizeField.prototype.valid = function() {
        return true;
    };

    SelectizeField.prototype.disabled = function() {
        return false;
    };

    SelectizeField.prototype.name = function(name) {
        if (name) {
            this.input.attr('name', name);
            return name;
        }

        return this.input.attr('name')
    };

    SelectizeField.prototype.value = function(val) {
        if (typeof val !== 'undefined') {
            val = typeof val === 'string' ? val.length ? val.split(',') : [] : val;

            for (var i = val.length - 1; i >= 0; i--) {
                this.selectize.addOption({ text: val[i], value: val[i] });
            }

            this.selectize.setValue(val);
        }

        return this.selectize.items;
    };

    SelectizeField.prototype.reset = function() {
        this.value('');
    };

    SelectizeField.prototype.formValues = function() {
        var o = {};
        o[this.name()] = this.value().join(',');
        return o;
    };

    root.Selectize = SelectizeField;
})();
