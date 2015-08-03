(function () {
    var root = window || {};
    root = root.GravJS = root.GravJS || {};

    function addTypes (form, factory) {
        var name = factory.getName(),
            types = factory.getTypes();

        for (var i = types.length - 1; i >= 0; i--) {
            form.types[types[i]] = name;

            if (form.scanned) {
                scan(form, type);
            }
        }
    }

    function scan (form, type) {
        for (var i = form.elements.length - 1; i >= 0; i--) {
            if (!type || form.elements[i].type === type) {
                form.elements.splice(i, 1);
            }
        }

        if (Object.keys(form.types).length === 0 || (type && !form.types[type])) {
            return;
        }

        form.findElements().each(function () {
            var el = $(this),
                type = el.data(form.dataIndicator);

            if (form.types[type]) {
                var factory = form.factories[form.types[type]],
                    element = new factory(el, form),
                    name = element.name();

                if (typeof form.toggleables[name] !== 'undefined') {
                    linkToggle(element, form.toggleables[name]);
                    delete form.toggleables[name];
                }

                el.data('grav-field-instance', element);

                form.elements.push({ type: type, element: element });
            }
        });
    }

    function scanToggleable (form) {
        form.toggleables = {};
        form.findElements('toggleable').each(function () {
            var el = $(this);
            form.toggleables[el.data('grav-field-name')] = el;
        });
    }

    function linkToggle (element, toggleable) {
        $(element).on('change', function (value) {
            toggleable.find('input').prop('checked', true);
            toggleable.siblings('label').css('opacity', 1);
            element.disabled(false);
        });

        toggleable.find('input').on('change', function () {
            var el = $(this),
                on = el.is(':checked');

            toggleable.siblings('label').css('opacity', on ? 1 : 0.7);
            element.disabled(!on);
            if (!on) {
                element.reset();
            }
        });

        var on = toggleable.find('input').is(':checked');
        toggleable.siblings('label').css('opacity', on ? 1 : 0.7);
        element.disabled(!on);
        if (!on) {
            element.reset();
        }
    }

    var Form = function (el, options) {
        options = options || {};

        this.form = $(el);
        this.form.data('grav-form-instance', this);
        this.form.on('submit', function (e) {
            this.submit(this.ajax);
            e.preventDefault();
            return false;
        }.bind(this));

        this.scanned = false;

        this.fieldIndicator = options.fieldIndicator || 'data-grav-field';
        this.dataIndicator = options.dataIndicator || (this.fieldIndicator.indexOf('data-') === 0 ? this.fieldIndicator.substr(5) : 'grav-field');
        this.ajax = options.ajax || false;

        this.elements = [];

        this.factories = {};
        this.types = {};

        if (typeof options.globalFactories === 'undefined' || options.globalFactories) {
            for (var name in Form.factories) { if (Form.factories.hasOwnProperty(name)) {
                    this.registerFactory(Form.factories[name]);
                }
            }
        }

        scanToggleable(this);
        scan(this);
        this.scanned = true;

        //Refresh root.currentValues as toggleables have been initialized
        (root || window.GravJS).currentValues = getState();
    };

    Form.factories = {};

    Form.findElements = function(el, selector, notIn, notSelf) {
        el = $(el);
        notIn = notIn || selector,
        notSelf = notSelf ? true : false;

        return el.find(selector).filter(function() {
            var parent = notSelf ? $(this) : $(this).parent();
                nearestMatch = parent.closest(notIn);
            return nearestMatch.length == 0 || el.find(nearestMatch).length == 0;
        });
    };

    Form.registerFactory = function (factory, context) {
        context = context || Form.factories;
        context[factory.getName()] = factory;
        return true;
    };

    Form.extendFactory = function (parentName, factory, context) {
        context = context || Form.factories;

        if (!context[parentName]) {
            return false;
        }

        return Form.registerFactory(factory.getName(), $.extend({}, context[parentName], factory));
    };

    Form.prototype.findElements = function(type) {
        var selector = '[' + this.fieldIndicator + (type ? '="' + type + '"' : '') + ']';

        return Form.findElements(this.form, selector);
    };

    Form.prototype.registerFactory = function(factory) {
        var registered = Form.registerFactory(factory, this.factories);

        if (registered) {
            addTypes(this, this.factories[factory.getName()]);
        }
    };

    Form.prototype.extendFactory = function(parentName, factory) {
        var registered = Form.extendFactory(parentN, factory, this.factories);

        if (registered) {
            addTypes(this, this.factories[factory.getName()]);
        }
    };

    Form.prototype.getElements = function() {
        if (!this.scanned) {
            scan(this);
            this.scanned = true;
        }

        return this.elements;
    };

    Form.prototype.getValues = function(all) {
        var elements = this.getElements(),
            values = {};

        for (var i = elements.length - 1; i >= 0; i--) {
            var e = elements[i].element;

            if (!all && (!e.valid() || e.disabled())) {
                continue;
            }

            $.extend(values, e.formValues());
        }

        return values;
    };

    Form.prototype.submit = function(ajax) {
        var action = this.form.attr('action') || document.location,
            method = this.form.attr('method') || 'POST',
            values = {};

        // Get form values that are not handled by JS framework
        Form.findElements(this.form, 'input, textarea', '', false).each(function(input) {
            var input = $(this),
                name = input.attr('name'),
                value = input.val();

            if (name) {
                values[name] = value;
            }
        });

        $.extend(values, this.getValues());

        if (!values.task) {
            values.task = 'save';
        }

        if (!ajax) {
            var form = $('<form>').attr({ method: method, action: action });

            for (var name in values) { if (values.hasOwnProperty(name)) {
                    $('<input>').attr({ type: 'hidden', name: name, value: values[name] }).appendTo(form);
                }
            }

            return form.appendTo('body').submit();
        } else {
            return $.ajax({ method: method, url: action, data: values });
        }
    };

    root.Form = Form;
})();
