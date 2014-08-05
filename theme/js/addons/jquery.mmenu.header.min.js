/*	
 * jQuery mmenu header addon
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
!function(e){var t="mmenu",a="header";e[t].prototype["_addon_"+a]=function(){var n=this,r=this.opts[a],d=this.conf[a],s=e[t]._c,i=(e[t]._d,e[t]._e);s.add("header hasheader prev next title titletext"),i.add("updateheader");var o=e[t].glbl;if("boolean"==typeof r&&(r={add:r,update:r}),"object"!=typeof r&&(r={}),r=e.extend(!0,{},e[t].defaults[a],r),r.add){var h=r.content?r.content:'<a class="'+s.prev+'" href="#"></a><span class="'+s.title+'"></span><a class="'+s.next+'" href="#"></a>';e('<div class="'+s.header+'" />').prependTo(this.$menu).append(h)}var p=e("div."+s.header,this.$menu);if(p.length&&this.$menu.addClass(s.hasheader),r.update&&p.length){var l=p.find("."+s.title),u=p.find("."+s.prev),f=p.find("."+s.next),c="#"+o.$page.attr("id");u.add(f).on(i.click,function(t){t.preventDefault(),t.stopPropagation();var a=e(this).attr("href");"#"!==a&&(a==c?n.$menu.trigger(i.close):e(a,n.$menu).trigger(i.open))}),e("."+s.panel,this.$menu).each(function(){var t=e(this),a=e("."+d.panelHeaderClass,t).text(),n=e("."+d.panelPrevClass,t).attr("href"),o=e("."+d.panelNextClass,t).attr("href");a||(a=e("."+s.subclose,t).text()),a||(a=r.title),n||(n=e("."+s.subclose,t).attr("href")),t.off(i.updateheader).on(i.updateheader,function(e){e.stopPropagation(),l[a?"show":"hide"]().text(a),u[n?"show":"hide"]().attr("href",n),f[o?"show":"hide"]().attr("href",o)}),t.on(i.open,function(){e(this).trigger(i.updateheader)})}).filter("."+s.current).trigger(i.updateheader)}},e[t].defaults[a]={add:!1,content:!1,update:!1,title:"Menu"},e[t].configuration[a]={panelHeaderClass:"Header",panelNextClass:"Next",panelPrevClass:"Prev"},e[t].addons=e[t].addons||[],e[t].addons.push(a)}(jQuery);