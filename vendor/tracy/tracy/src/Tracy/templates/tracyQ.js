/**
 * tracyQ
 *
 * This file is part of the Tracy.
 * Copyright (c) 2004, 2014 David Grudl (http://davidgrudl.com)
 */

var Tracy = Tracy || {};

(function(){

	// supported cross-browser selectors: #id  |  div  |  div.class  |  .class
	var Query = Tracy.Query = function(selector) {
		if (typeof selector === "string") {
			selector = this._find(document, selector);

		} else if (!selector || selector.nodeType || selector.length === undefined || selector === window) {
			selector = [selector];
		}

		for (var i = 0, len = selector.length; i < len; i++) {
			if (selector[i]) { this[this.length++] = selector[i]; }
		}
	};

	Query.factory = function(selector) {
		return new Query(selector);
	};

	Query.prototype.length = 0;

	Query.prototype.find = function(selector) {
		return new Query(this._find(this[0], selector));
	};

	Query.prototype._find = function(context, selector) {
		if (!context || !selector) {
			return [];

		} else if (document.querySelectorAll) {
			return context.querySelectorAll(selector);

		} else if (selector.charAt(0) === '#') { // #id
			return [document.getElementById(selector.substring(1))];

		} else { // div  |  div.class  |  .class
			selector = selector.split('.');
			var elms = context.getElementsByTagName(selector[0] || '*');

			if (selector[1]) {
				var list = [], pattern = new RegExp('(^|\\s)' + selector[1] + '(\\s|$)');
				for (var i = 0, len = elms.length; i < len; i++) {
					if (pattern.test(elms[i].className)) { list.push(elms[i]); }
				}
				return list;
			} else {
				return elms;
			}
		}
	};

	Query.prototype.dom = function() {
		return this[0];
	};

	Query.prototype.each = function(callback) {
		for (var i = 0; i < this.length; i++) {
			if (callback.apply(this[i]) === false) { break; }
		}
		return this;
	};

	// cross-browser event attach
	Query.prototype.bind = function(event, handler) {
		if (document.addEventListener && (event === 'mouseenter' || event === 'mouseleave')) { // simulate mouseenter & mouseleave using mouseover & mouseout
			var old = handler;
			event = event === 'mouseenter' ? 'mouseover' : 'mouseout';
			handler = function(e) {
				for (var target = e.relatedTarget; target; target = target.parentNode) {
					if (target === this) { return; } // target must not be inside this
				}
				old.call(this, e);
			};
		}

		return this.each(function() {
			var elem = this, // fixes 'this' in iE
				data = elem.tracy ? elem.tracy : elem.tracy = {},
				events = data.events = data.events || {}; // use own handler queue

			if (!events[event]) {
				var handlers = events[event] = [],
					generic = function(e) { // dont worry, 'e' is passed in IE
					if (!e.target) {
						e.target = e.srcElement;
					}
					if (!e.preventDefault) {
						e.preventDefault = function() { e.returnValue = false; };
					}
					if (!e.stopPropagation) {
						e.stopPropagation = function() { e.cancelBubble = true; };
					}
					e.stopImmediatePropagation = function() { this.stopPropagation(); i = handlers.length; };
					for (var i = 0; i < handlers.length; i++) {
						handlers[i].call(elem, e);
					}
				};

				if (document.addEventListener) { // non-IE
					elem.addEventListener(event, generic, false);
				} else if (document.attachEvent) { // IE < 9
					elem.attachEvent('on' + event, generic);
				}
			}

			events[event].push(handler);
		});
	};

	// adds class to element
	Query.prototype.addClass = function(className) {
		return this.each(function() {
			this.className = (this.className.replace(/^|\s+|$/g, ' ').replace(' '+className+' ', ' ') + ' ' + className).replace(/^\s+|\s+$/g,'');
		});
	};

	// removes class from element
	Query.prototype.removeClass = function(className) {
		return this.each(function() {
			this.className = this.className.replace(/^|\s+|$/g, ' ').replace(' '+className+' ', ' ').replace(/^\s+|\s+$/g,'');
		});
	};

	// tests whether element has given class
	Query.prototype.hasClass = function(className) {
		return this[0] && this[0].className && this[0].className.replace(/^|\s+|$/g, ' ').indexOf(' '+className+' ') > -1;
	};

	Query.prototype.show = function() {
		Query.displays = Query.displays || {};
		return this.each(function() {
			var tag = this.tagName;
			if (!Query.displays[tag]) {
				Query.displays[tag] = (new Query(document.body.appendChild(document.createElement(tag)))).css('display');
			}
			this.style.display = Query.displays[tag];
		});
	};

	Query.prototype.hide = function() {
		return this.each(function() {
			this.style.display = 'none';
		});
	};

	Query.prototype.css = function(property) {
		if (this[0] && this[0].currentStyle) {
			return this[0].currentStyle[property];
		} else if (this[0] && window.getComputedStyle) {
			return document.defaultView.getComputedStyle(this[0], null).getPropertyValue(property)
		}
	};

	Query.prototype.data = function() {
		if (this[0]) {
			return this[0].tracy ? this[0].tracy : this[0].tracy = {};
		}
	};

	Query.prototype._trav = function(elem, selector, fce) {
		selector = selector.split('.');
		while (elem && !(elem.nodeType === 1 &&
			(!selector[0] || elem.tagName.toLowerCase() === selector[0]) &&
			(!selector[1] || (new Query(elem)).hasClass(selector[1])))) {
			elem = elem[fce];
		}
		return new Query(elem || []);
	};

	Query.prototype.closest = function(selector) {
		return this._trav(this[0], selector, 'parentNode');
	};

	Query.prototype.prev = function(selector) {
		return this._trav(this[0] && this[0].previousSibling, selector, 'previousSibling');
	};

	Query.prototype.next = function(selector) {
		return this._trav(this[0] && this[0].nextSibling, selector, 'nextSibling');
	};

	// returns total offset for element
	Query.prototype.offset = function(coords) {
		if (coords) {
			return this.each(function() {
				var elem = this, ofs = {left: -coords.left || 0, top: -coords.top || 0};
				while (elem = elem.offsetParent) {
					ofs.left += elem.offsetLeft; ofs.top += elem.offsetTop;
				}
				this.style.left = -ofs.left + 'px';
				this.style.top = -ofs.top + 'px';
			});
		} else if (this[0]) {
			var elem = this[0], res = {left: elem.offsetLeft, top: elem.offsetTop};
			while (elem = elem.offsetParent) {
				res.left += elem.offsetLeft; res.top += elem.offsetTop;
			}
			return res;
		}
	};

	// returns current position or move to new position
	Query.prototype.position = function(coords) {
		if (coords) {
			return this.each(function() {
				if (this.tracy && this.tracy.onmove) {
					this.tracy.onmove.call(this, coords);
				}
				for (var item in coords) {
					this.style[item] = coords[item] + 'px';
				}
			});
		} else if (this[0]) {
			return {
				left: this[0].offsetLeft, top: this[0].offsetTop,
				right: this[0].style.right ? parseInt(this[0].style.right, 10) : 0, bottom: this[0].style.bottom ? parseInt(this[0].style.bottom, 10) : 0,
				width: this[0].offsetWidth, height: this[0].offsetHeight
			};
		}
	};

	// makes element draggable
	Query.prototype.draggable = function(options) {
		var elem = this[0], dE = document.documentElement, started;
		options = options || {};

		(options.handle ? new Query(options.handle) : this).bind('mousedown', function(e) {
			var $el = new Query(options.handle ? elem : this);
			e.preventDefault();
			e.stopPropagation();

			if (Query.dragging) { // missed mouseup out of window?
				return dE.onmouseup(e);
			}

			var pos = $el.position(),
				deltaX = options.rightEdge ? pos.right + e.clientX : pos.left - e.clientX,
				deltaY = options.bottomEdge ? pos.bottom + e.clientY : pos.top - e.clientY;

			Query.dragging = true;
			started = false;

			dE.onmousemove = function(e) {
				e = e || window.event;
				if (!started) {
					if (options.draggedClass) {
						$el.addClass(options.draggedClass);
					}
					if (options.start) {
						options.start(e, $el);
					}
					started = true;
				}

				var pos = {};
				pos[options.rightEdge ? 'right' : 'left'] = options.rightEdge ? deltaX - e.clientX : e.clientX + deltaX;
				pos[options.bottomEdge ? 'bottom' : 'top'] = options.bottomEdge ? deltaY - e.clientY : e.clientY + deltaY;
				$el.position(pos);
				return false;
			};

			dE.onmouseup = function(e) {
				if (started) {
					if (options.draggedClass) {
						$el.removeClass(options.draggedClass);
					}
					if (options.stop) {
						options.stop(e || window.event, $el);
					}
				}
				Query.dragging = dE.onmousemove = dE.onmouseup = null;
				return false;
			};

		}).bind('click', function(e) {
			if (started) {
				e.stopImmediatePropagation();
			}
		});

		return this;
	};

})();
