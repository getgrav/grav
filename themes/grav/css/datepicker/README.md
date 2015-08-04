# Custom build of kendo-ui-core

Compiled by: Gert
Source: https://github.com/telerik/kendo-ui-core

## How to reproduce

1. Checkout source
2. `npm install`
3. Change `styles/web/common/core.less`

```
@import "mixins.less";
@import "base.less";
// @import "responsivepanel.less";
// @import "forms.less";
// @import "window.less";
// @import "tabstrip.less";
// @import "panelbar.less";
// @import "menu.less";
@import "calendar.less";
@import "inputs.less";
// @import "notification.less";
// @import "progressbar.less";
// @import "slider.less";
// @import "tooltip.less";
// @import "toolbar.less";
// @import "splitter.less";
// @import "ie7.less";
// @import "virtuallist.less";
// @import "../../common/transitions.less";
```

4. Change `styles/web/kendo.flat.less` color variables

```
@accent: lighten(#349886, 10%);
@base: #253A47;
@background: #fff;

@border-radius: 3px;

@normal-background: #fff;
@normal-text-color: #737C81;
@normal-gradient: none;
@hover-background: lighten(@accent, 5%);
@hover-text-color: #fff;
@hover-gradient: none;
@selected-background: darken(@accent, 5%);
@selected-text-color: #fff;
@selected-gradient: none;
``

5. `grunt styles`
6. Copy relevant files from `dist` folder
