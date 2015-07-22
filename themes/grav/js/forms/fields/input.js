(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};
    root = root.FormFields = root.FormFields || {};

    var Input = function (el, form) {
        el = $(el);

        var parent = el.is('[' + form.fieldIndicator + ']') ? el : el.closest('[' + form.fieldIndicator + ']'),
            input = parent.prop('tagName').toUpperCase() === 'INPUT' ? parent : parent.find('input'),
            type = parent.data(form.fieldIndicator);

        this.el = parent;
        this.input = input;

        this._disabled = parent.data('grav-disabled') || false;
        this._default = parent.data('grav-default') || '';
    };

    Input.getName = function () {
        return 'input';
    };

    Input.getTypes = function () {
        return [ 'text', 'hidden' ];
    };

    Input.prototype.valid = function() {
        return true;
    };

    Input.prototype.disabled = function(state) {
        if (typeof state !== 'undefined') {
            this._disabled = state ? true : false;
        }

        return this._disabled;
    };

    Input.prototype.name = function(name) {
        if (name) {
            this.input.attr('name', name);
            return name;
        }

        return this.input.attr('name')
    };

    Input.prototype.value = function(val) {
        if (typeof val !== 'undefined') {
            this.input.val(val);
        }

        return this.input.val();
    };

    Input.prototype.reset = function() {
        this.value(this._default);
    };

    Input.prototype.formValues = function() {
        var o = {};
        o[this.name()] = this.value();
        return o;
    };

    Input.prototype.onChange = function(eh) {
        var self = this;
        this.input.on('keyup', function () { eh.call(self, self.value()); });
    };

    root.Input = Input;
})();
