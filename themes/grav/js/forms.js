$(function () {

    if (typeof window.GravJS === 'undefined' || !window.GravJS.Form) {
        console.warn('Dependencies for Grav Forms are not loaded.');
        return;
    }

    // Register all FormFields that were loaded
    if (typeof window.GravJS.FormFields === 'object') {
        for (var key in window.GravJS.FormFields) { if (window.GravJS.FormFields.hasOwnProperty(key)) {
                GravJS.Form.registerFactory(GravJS.FormFields[key]);
            }
        }
    }

    window.formInstances = [];
    $('[data-grav-form]').each(function () {
        window.formInstances.push(new GravJS.Form($(this)));
    })
});
