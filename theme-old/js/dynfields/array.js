(function($){
    String.prototype.capitalize = function() {
        return this.charAt(0).toUpperCase() + this.slice(1);
    };

    var GROUP = -1;

    var DynFields2 = {
        init: function () {
            var container = $('[data-grav-array]'), blockParent, options;
            DynFields2.container = container;
            container.parent().on('click', '[data-grav-addfield]', DynFields2.addField.bind(DynFields2));
            container.on('click', '[data-grav-remfield]', DynFields2.remField.bind(DynFields2));

            $.each(DynFields2.container, function(index, block){
                block       = $(block);
                blockParent = $(block.parents('.grav-array'));
                options     = DynFields2.getOptions(block);

                if (options && options.sortable_root){
                    blockParent.nestable({
                        rootClass:       'grav-array',
                        handleClass:     'dd-root-handle',
                        maxDepth:        1,
                        expandBtnHTML:   false,
                        collapseBtnHTML: false,
                        group:           ++GROUP
                    });

                    blockParent.on('change', function(){
                        DynFields2.updateNames(block);
                    });
                } else {
                    block.find('.dd-root-handle').remove();
                }


                if (options && options.sortable_children){
                    $.each(block.find('.dd3-content'), function(index, content){
                        DynFields2.makeSortable(content);
                    });
                } else {
                    block.find('.dd3-content .dd-grav-handle').remove();
                }
            });
        },

        getOptions: function(container){
            var data = container.data('grav-array'),
                options;

            $.each(data, function(name, values){
                options = values.options;
            });

            return options;
        },

        getSchema: function(container){
            var data = container.data('grav-array'),
                schema;

            $.each(data, function(name, values){
                schema = values.schema;
            });

            return schema;
        },

        addField: function(event){
            var element   = $(event.target),
                location  = 'insertAfter',
                container = element.parents('[data-grav-array]'),
                parents   = element.parents('li');

            if (!container.length) {
                container = element.next('[data-grav-array]');
                location = 'appendTo';
            }
            if (!parents.length)   parents   = container.last();

            var schema  = DynFields2.buildSchema(container),
                li      = $('<li  class="dd-item dd3-item" />').html(schema)[location](parents);

            DynFields2.updateNames(container);
            DynFields2.makeSortable(li.find('.dd3-content'));
        },

        remField: function(event){
            var element   = $(event.target),
                container = element.parents('[data-grav-array]');

            element.parents('li').remove();

            DynFields2.updateNames(container);
        },

        updateNames: function(container){
            var items, name;

            $.each(container.children(), function(index, item){
                items = $(item).find('[name]');

                $.each(items, function(key, input){
                    input = $(input);
                    input.attr('name', input.attr('name').replace(/\[\w\]/, '[' + index + ']'));
                });
            });

        },

        makeSortable: function(context){
            context = $(context);
            context.nestable({
                maxDepth:        1,
                expandBtnHTML:   false,
                collapseBtnHTML: false,
                listClass:       'dd-grav-list',
                itemClass:       'dd-grav-item',
                rootClass:       'dd3-content',
                handleClass:     'dd-grav-handle',
                group:           ++GROUP
            });

            context.on('change', function(){
                DynFields2.updateNames(context.parents('[data-grav-array]'));
            });
        },

        buildSchema: function(container){
            var data      = container.data('grav-array'),
                options   =     DynFields2.getOptions(container),
                html      = [],
                input     = '',
                inputName = '',
                index;

            if (options && options.sortable_root) html.push(' <div class="dd-handle dd3-handle dd-root-handle"></div>');
            html.push(' <div class="dd-grav-actions">');
            html.push('     <span data-grav-remfield class="button fa fa-minus"></span>');
            html.push('     <span data-grav-addfield class="button fa fa-plus"></span></span>');
            html.push(' </div>');
            html.push(' <ol class="dd3-content dd-grav-list">');

            $.each(DynFields2.getSchema(container), function(key, value){
                html.push('<li class="dd-grav-item">');
                if (options && options.sortable_children) html.push(' <div class="dd-handle dd3-handle dd-grav-handle"></div>');
                html.push(' <span class="label">' + (value.label || key.capitalize()) + '</span>');

                inputName = name + '[X]' + '[' + key + ']';
                switch(value.type || 'input'){
                    case 'text': case 'hidden':
                        input = '<input type="' + (value.type || 'input') + '" placeholder="' + (value.placeholder || '') + '" name="' + inputName + '" />';
                        break;

                    case 'textarea':
                        input = '<textarea placeholder="' + (value.placeholder || '') + '" name="' + inputName + '"></textarea>';
                        break;

                    case 'select':
                        input  = '<select name="' + inputName + '">';
                        $.each(value.options || [], function(sValue, sLabel){
                            input += '<option value="' + sValue + '">' + sLabel + '</option>';
                        });
                        input += '</select>';
                        break;

                    case 'radio':
                        input = '';
                        index = 0;
                        $.each(value.options || [], function(sValue, sLabel){
                            input += '<label>';
                            input += '  <input type="' + value.type + '" name="' + inputName + '" value="' + sValue + '" ' + (!index ? 'checked' : '') + '/> ';
                            input +=    sLabel;
                            input += '</label> ';
                            index++;
                        });
                        break;
                }

                html.push(input);
                html.push('</li>');
            });

            html.push(' </ol>');

            return html.join("\n");
        }
    };

    $(DynFields2.init);

})(jQuery);
