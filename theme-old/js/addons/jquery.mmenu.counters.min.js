/*	
 * jQuery mmenu counters addon
 * @requires mmenu 4.0.0 or later
 *
 * mmenu.frebsite.nl
 *	
 * Copyright (c) Fred Heusschen
 * www.frebsite.nl
 *
 * Dual licensed under the MIT and GPL licenses.
 * http://en.wikipedia.org/wiki/MIT_License
 * http://en.wikipedia.org/wiki/GNU_General_Public_License
 */
!function(t){var e="mmenu",n="counters";t[e].prototype["_addon_"+n]=function(){var o=this,u=this.opts[n],a=t[e]._c,r=t[e]._d,d=t[e]._e;a.add("counter noresults"),d.add("updatecounters"),"boolean"==typeof u&&(u={add:u,update:u}),"object"!=typeof u&&(u={}),u=t.extend(!0,{},t[e].defaults[n],u),u.count&&(t[e].deprecated('the option "count" for counters, the option "update"'),u.update=u.count),this.__refactorClass(t("em."+this.conf.counterClass,this.$menu),"counter");var s=t("."+a.panel,this.$menu);if(u.add&&s.each(function(){var e=t(this),n=e.data(r.parent);if(n){var o=t('<em class="'+a.counter+'" />'),u=n.find("> a."+a.subopen);u.parent().find("em."+a.counter).length||u.before(o)}}),u.update){var c=t("em."+a.counter,this.$menu);c.off(d.updatecounters).on(d.updatecounters,function(t){t.stopPropagation()}).each(function(){var e=t(this),n=t(e.next().attr("href"),o.$menu);n.is("."+a.list)||(n=n.find("> ."+a.list)),n.length&&e.on(d.updatecounters,function(){var t=n.children().not("."+a.label).not("."+a.subtitle).not("."+a.hidden).not("."+a.noresults);e.html(t.length)})}).trigger(d.updatecounters),this.$menu.on(d.update,function(){c.trigger(d.updatecounters)})}},t[e].defaults[n]={add:!1,update:!1},t[e].configuration.counterClass="Counter",t[e].addons=t[e].addons||[],t[e].addons.push(n)}(jQuery);