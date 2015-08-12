(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};
    root = root.FormFields = root.FormFields || {};

    var CheckboxesField = function (el, form) {
        el = $(el);
        this.el = el.is('[' + form.fieldIndicator + ']') ? el : el.closest('[' + form.fieldIndicator + ']');

        this.keys = this.el.data('grav-keys') || false;

        this._disabled = this.el.data('grav-disabled') || false;
        this._default = this.el.data('grav-default') || '';
    };

    CheckboxesField.getName = function () {
        return 'checkboxes';
    };

    CheckboxesField.getTypes = function () {
        return [ 'checkboxes' ];
    };

    CheckboxesField.prototype.valid = function() {
        return true;
    };

    CheckboxesField.prototype.disabled = function(state) {
        if (typeof state !== 'undefined') {
            this._disabled = state ? true : false;
            this.el.css('opacity', state ? 0.6 : 1);
        }

        return this._disabled;
    };

    CheckboxesField.prototype.name = function(name) {
        if (name) {
            this.el.data('grav-field-name', name);
            return name;
        }

        return this.el.data('grav-field-name')
    };

    CheckboxesField.prototype.value = function(val) {
        var useKeys = this.keys,
            values = useKeys ? {} : [];

        if (typeof val !== 'undefined') {
            this.el.find('input').each(function () {
                var checked = false;

                if (useKeys && typeof val[$(this).attr('name')] !== 'undefined') {
                    checked = val[$(this).attr('name')];
                } else if (!useKeys && val.indexOf($(this).val()) !== -1) {
                    checked = true;
                }

                $(this).prop('checked', checked);
            });

            return val;
        }

        this.el.find('input').each(function () {
            if (useKeys) {
                values[$(this).attr('name')] = $(this).is(':checked');
            } else if ($(this).is(':checked')) {
                values.push($(this).val());
            }
        });

        return values;
    };

    CheckboxesField.prototype.reset = function() {
        this.value(this._default);
    };

    CheckboxesField.prototype.formValues = function() {
        var values = this.value(),
            name = this.name(),
            formValues = {};

        for (var key in values) { if (values.hasOwnProperty(key)) {
                formValues[key] = values[key] ? '1' : '0';
            }
        }

        return formValues;
    };

    CheckboxesField.prototype.onChange = function(eh) {
        var self = this;
        this.el.find('input').on('change', function () { eh.call(self, self.value()); });
    };

    root.Checkboxes = CheckboxesField;
})();
