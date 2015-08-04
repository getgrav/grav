(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};
    root = root.FormFields = root.FormFields || {};

    var ToggleField = function (el, form) {
        el = $(el);
        this.el = el.is('[' + form.fieldIndicator + ']') ? el : el.closest('[' + form.fieldIndicator + ']');

        this._disabled = this.el.data('grav-disabled') || false;
        this._default = this.el.data('grav-default') || '';
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
            this.el.data('grav-field-name', name);
            return name;
        }

        return this.el.data('grav-field-name')
    };

    ToggleField.prototype.value = function(val) {
        if (typeof val !== 'undefined') {
            this.el.find('input').prop('checked', false).filter('[value="' + val + '"]').prop('checked', true);
            return val;
        }

        return this.el.find('input:checked').val();
    };

    ToggleField.prototype.reset = function() {
        this.value(this._default);
    };

    ToggleField.prototype.formValues = function() {
        var o = {};
        o[this.name()] = this.value();
        return o;
    };

    root.Toggle = ToggleField;
})();
