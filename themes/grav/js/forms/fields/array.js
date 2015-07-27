(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};
    root = root.FormFields = root.FormFields || {};

    var ArrayField = function (el, form) {
        el = $(el);
        this.el = el.is('[' + form.fieldIndicator + ']') ? el : el.closest('[' + form.fieldIndicator + ']');

        this.el.on('click', '[data-grav-array-action="add"]', this.add.bind(this));
        this.el.on('click', '[data-grav-array-action="rem"]', this.remove.bind(this));
        this.el.on('keyup', '[data-grav-array-type="key"]', this.update.bind(this));
    };

    ArrayField.getName = function () {
        return 'array';
    };

    ArrayField.getTypes = function () {
        return [ 'array' ];
    };

    ArrayField.prototype.valid = function() {
        return true;
    };

    ArrayField.prototype.disabled = function() {
        return false;
    };

    ArrayField.prototype.name = function(name) {
        if (name) {
            this.el.data('grav-array-name', name);
            return name;
        }

        return this.el.data('grav-array-name')
    };

    ArrayField.prototype.value = function(val) {
        if (typeof val === 'object') {
            // Remove old
            this.el.find('[data-grav-array-type="row"]').remove();

            var container = this.el.find('[data-grav-array-type="container"]');
            for (var key in val) { if (val.hasOwnProperty(key)) {
                    container.append(this._getNewField(key, val[key]));
                }
            }

            return val;
        }

        var values = {};
        this.el.find('[data-grav-array-type="value"]').each(function () {
            var key = $(this).attr('name'),
                value = $(this).val();

            values[key] = value;
        });

        return values;
    };

    ArrayField.prototype.reset = function() {
        this.value('');
    };

    ArrayField.prototype.formValues = function() {
        var values = this.value(),
            name = this.name(),
            formValues = {};

        for (var key in values) { if (values.hasOwnProperty(key)) {
                formValues[name + '[' + key + ']'] = values[key];
            }
        }

        return formValues;
    };

    ArrayField.prototype.add = function() {
        $(this._getNewField()).insertAfter($(event.target).closest('[data-grav-array-type="row"]'));
    };

    ArrayField.prototype.remove = function() {
        $(event.target).closest('[data-grav-array-type="row"]').remove();
    };

    ArrayField.prototype.update = function() {
        var keyField = $(event.target),
            valueField = keyField.closest('[data-grav-array-type="row"]').find('[data-grav-array-type="value"]');

        valueField.attr('name', keyField.val());
    };

    ArrayField.prototype._getNewField = function(key, value) {
        var name = this.name(),
            placeholder = {
                key: this.el.data('grav-array-keyname') || 'Key',
                val: this.el.data('grav-array-valuename') || 'Value'
            };

        key = key || '';
        value = value || '';

        return '<div class="form-row" data-grav-array-type="row">' +
            '<input data-grav-array-type="key" type="text" value="' + key + '"  placeholder="' + placeholder.key + '" />' +
            '<input data-grav-array-type="value" type="text" name="' + key + '" value="' + value + '" placeholder="' + placeholder.val + '" />' +
            '<span data-grav-array-action="rem" class="fa fa-minus"></span>' +
            '<span data-grav-array-action="add" class="fa fa-plus"></span>' +
        '</div>';
    };

    root.Array = ArrayField;
})();
