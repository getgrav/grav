(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};
    root = root.FormFields = root.FormFields || {};

    var ToggleField = function (el, form) {
        el = $(el);
        this.el = el.is('[' + form.fieldIndicator + ']') ? el : el.closest('[' + form.fieldIndicator + ']');
    };

    ToggleField.getName = function () {
        return 'toggle';
    };

    ToggleField.getTypes = function () {
        return [ 'toggle' ];
    };

    ToggleField.prototype.valid = function() {
        return true;
    };

    ToggleField.prototype.disabled = function() {
        return false;
    };

    ToggleField.prototype.name = function(name) {
        if (name) {
            this.el.find('input').attr('name', name);
            return name;
        }

        return this.el.find('input').attr('name');
    };

    ToggleField.prototype.value = function(val) {
        if (typeof val !== 'undefined') {
            this.el.find('input').prop('checked', false).filter('[value="' + val + '"]').prop('checked', true);
            return val;
        }

        return this.el.find('input:checked').val();
    };

    ToggleField.prototype.reset = function() {
        this.value('');
    };

    ToggleField.prototype.formValues = function() {
        var o = {};
        o[this.name()] = this.value();
        return o;
    };

    root.Toggle = ToggleField;
})();
