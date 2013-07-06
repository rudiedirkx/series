
/**
 * Todo:
 * - Asset loading (JS, CSS)
 */

(function(W, D) {

	"use strict";

	// try {

	var html = D.documentElement,
		head = html.getElementsByTagName('head')[0];

	/* <domready */
	var domReadyAttached = false,
		domIsReady = false;
	/* domready> */

	/* <element_show */
	var cssDisplays = {};
	/* element_show> */

	/* <ifsetor */
	function $ifsetor(pri, sec) {
		return pri !== undefined ? pri : sec;
	}
	/* ifsetor> */

	function $arrayish(obj) {
		return typeof obj.length == 'number' && typeof obj != 'string' && obj.constructor != Object;
	}

	/* <array */
	function $array(list) {
		var arr = [];
		$each(list, function(el, i) {
			arr.push(el);
		});
		return arr;
	}
	/* array> */

	/* <class */
	function $class(obj) {
		var code = String(obj.constructor);
		return code.match(/ (.+?)[\(\]]/)[1];
	}
	/* class> */

	function $is_a(obj, type) {
		return window[type] && obj instanceof window[type];
	}

	/* <serialize */
	function $serialize(o, prefix) {
		var q = [];
		$each(o, function(v, k) {
			var name = prefix ? prefix + '[' + k + ']' : k,
			v = o[k];
			if ( typeof v == 'object' ) {
				q.push($serialize(v, name));
			}
			else {
				q.push(name + '=' + encodeURIComponent(v));
			}
		});
		return q.join('&');
	}
	/* serialize> */


	function $each(source, callback, context) {
		if ( $arrayish(source) ) {
			for ( var i=0, L=source.length; i<L; i++ ) {
				callback.call(context, source[i], i, source);
			}
		}
		else {
			for ( var k in source ) {
				if ( source.hasOwnProperty(k) ) {
					callback.call(context, source[k], k, source);
				}
			}
		}

		return source;
	}

	function $extend(Hosts, proto, Super) {
		if ( !(Hosts instanceof Array) ) {
			Hosts = [Hosts];
		}

		$each(Hosts, function(Host) {
			if ( Super ) {
				Host.prototype = Super;
				Host.prototype.constructor = Host;
			}

			var methodOwner = Host.prototype ? Host.prototype : Host;
			$each(proto, function(fn, name) {
				methodOwner[name] = fn;

				if ( Host == Element && !Elements.prototype[name] ) {
					Elements.prototype[name] = function() {
						return this.invoke(name, arguments);
					};
				}
			});
		});
	}

	/* <getter */
	function $getter(Host, prop, getter) {
		Object.defineProperty(Host.prototype, prop, {get: getter});
	}
	/* getter> */

	$extend(Array, {
		/* <array_invoke */
		invoke: function(method, args) {
			var results = [];
			this.forEach(function(el) {
				results.push( el[method].apply(el, args) );
			});
			return results;
		},
		/* array_invoke> */

		/* <array_contains */
		contains: function(obj) {
			return this.indexOf(obj) != -1;
		},
		/* array_contains> */

		/* <array_unique */
		unique: function() {
			var els = [];
			this.forEach(function(el) {
				els.contains(el) || els.push(el);
			});
			return els;
		},
		/* array_unique> */

		/* <array_each */
		each: Array.prototype.forEach,
		/* array_each> */

		/* <array_firstlast */
		first: function() {
			return this[0] || null;
		},
		last: function() {
			return this[this.length-1] || null;
		}
		/* array_firstlast> */
	});
	/* <array_defaultfilter */
	Array.defaultFilterCallback = function(item) {
		return !!item;
	};
	/* array_defaultfilter> */

	$extend(String, {
		/* <string_camel */
		camel: function() {
			// foo-bar => fooBar, -ms-foo => MsFoo
			return this.replace(/\-([^\-])/g, function(a, m) {
				return m.toUpperCase();
			});
		},
		uncamel: function() {
			return this.replace(/([A-Z])/g, function(a, m) {
				return '-' + m.toLowerCase();
			});
		}
		/* string_camel> */
	});

	var indexOf = [].indexOf,
		slice = [].slice,
		push = [].push,
		splice = [].splice,
		join = [].join,
		pop = [].join;

	/* <_date_now */
	typeof Date.now == 'function' || (Date.now = function() {
		return +new Date;
	});
	/* _date_now> */

	/* <_classlist */
	if (!('classList' in html)) {
		W.DOMTokenList = function DOMTokenList(el) {
			this._el = el;
			el.$classList = this;
			this._reinit();
		}
		$extend(W.DOMTokenList, {
			_reinit: function() {
				// Empty
				this.length = 0;

				// Fill
				var classes = this._el.className.trim();
				classes = classes ? classes.split(/\s+/g) : [];
				for ( var i=0, L=classes.length; i<L; i++ ) {
					push.call(this, classes[i]);
				}

				return this;
			},
			set: function() {
				this._el.className = join.call(this, ' ');
			},
			add: function(token) {
				push.call(this, token);
				this.set();
			},
			contains: function(token) {
				return indexOf.call(this, token) !== -1;
			},
			item: function(index) {
				return this[index] || null;
			},
			remove: function(token) {
				var i = indexOf.call(this, token);
				if ( i != -1 ) {
					splice.call(this, i, 1);
					this.set();
				}
			},
			toggle: function(token) {
				if ( this.contains(token) ) {
					return !!this.remove(token);
				}

				return !this.add(token);
			}
		});

		$getter(Element, 'classList', function() {
			return this.$classList ? this.$classList._reinit() : new W.DOMTokenList(this);
		});
	}
	/* _classlist> */

	/* <elements */
	function Elements(source, selector) {
		this.length = 0;
		source && $each(source, function(el, i) {
			el.nodeType === 1 && ( !selector || el.is(selector) ) && this.push(el);
		}, this);
	}
	$extend(Elements, {
		/* <elements_invoke */
		invoke: function(method, args) {
			var returnSelf = false,
				res = [],
				isElements = false;
			$each(this, function(el, i) {
				var retEl = el[method].apply(el, args);
				res.push( retEl );
				if ( retEl == el ) returnSelf = true;
				if ( retEl instanceof Element ) isElements = true;
			});
			return returnSelf ? this : ( isElements || !res.length ? new Elements(res) : res );
		},
		/* elements_invoke> */
		filter: function(filter) {
			if ( typeof filter == 'function' ) {
				return new Elements([].filter.call(this, filter));
			}
			return new Elements(this, filter);
		}
	}, new Array);
	/* elements> */

	/* <coords2d */
	function Coords2D(x, y) {
		this.x = x;
		this.y = y;
	}
	$extend(Coords2D, {
		/* <coords2d_add */
		add: function(coords) {
			return new Coords2D(this.x + coords.x, this.y + coords.y);
		},
		/* coords2d_add> */

		/* <coords2d_subtract */
		subtract: function(coords) {
			return new Coords2D(this.x - coords.x, this.y - coords.y);
		},
		/* coords2d_subtract> */

		/* <coords2d_tocss */
		toCSS: function() {
			return {
				left: this.x + 'px',
				top: this.y + 'px'
			};
		},
		/* coords2d_tocss> */

		/* <coords2d_join */
		join: function(glue) {
			glue == null && (glue = ',');
			return [this.x, this.y].join(glue);
		},
		/* coords2d_join> */

		/* <coords2d_equal */
		equal: function(coord) {
			return this.join() == coord.join();
		}
		/* coords2d_equal> */
	});
	/* coords2d> */

	/* <anyevent */
	function AnyEvent(e) {
		if ( typeof e == 'string' ) {
			this.originalEvent = null;
			e = {"type": e, "target": null};
		}
		else {
			this.originalEvent = e;
		}

		this.type = e.type;
		this.target = e.target || e.srcElement;
		this.relatedTarget = e.relatedTarget;
		this.fromElement = e.fromElement;
		this.toElement = e.toElement;
		// this.which = e.which;
		// this.keyCode = e.keyCode;
		this.key = e.keyCode || e.which;
		this.alt = e.altKey;
		this.ctrl = e.ctrlKey;
		this.shift = e.shiftKey;
		this.button = e.button || e.which;
		/* <anyevent_lmrclick */
		this.leftClick = this.button == 1;
		this.rightClick = this.button == 2;
		this.middleClick = this.button == 4 || this.button == 1 && this.key == 2;
		this.leftClick = this.leftClick && !this.middleClick;
		/* anyevent_lmrclick> */
		this.which = this.key || this.button;
		this.detail = e.detail;

		this.pageX = e.pageX;
		this.pageY = e.pageY;
		this.clientX = e.clientX;
		this.clientY = e.clientY;

		/* <anyevent_touches */
		this.touches = e.touches ? $array(e.touches) : null;

		if ( this.touches && this.touches[0] ) {
			this.pageX = this.touches[0].pageX;
			this.pageY = this.touches[0].pageY;
		}
		/* anyevent_touches> */

		/* <anyevent_pagexy */
		if ( this.pageX != null && this.pageY != null ) {
			this.pageXY = new Coords2D(this.pageX, this.pageY);
		}
		else if ( this.clientX != null && this.clientY != null ) {
			this.pageXY = new Coords2D(this.clientX, this.clientY).add(W.getScroll());
		}
		/* anyevent_pagexy> */

		this.data = e.dataTransfer || e.clipboardData;
		this.time = e.timeStamp || e.timestamp || e.time || Date.now();

		this.total = e.total || e.totalSize;
		this.loaded = e.loaded || e.position;
	}
	$extend(AnyEvent, {
		/* <anyevent_summary */
		summary: function(prefix) {
			prefix || (prefix = '');
			var summary = [];
			$each(this, function(value, name) {
				var original = value;
				if ( original && $is_a(original, 'Coords2D') ) {
					value = original.join();
				}
				else if ( original && typeof original == 'object' ) {
					value = $class(value);
					if ( original instanceof Event || name == 'touches' || typeof name == 'number' ) {
						value += ":\n" + AnyEvent.prototype.summary.call(original, prefix + '  ');
					}
				}
				summary.push(prefix + name + ' => ' + value);
			});
			return summary.join("\n");
		},
		/* anyevent_summary> */

		preventDefault: function(e) {
			if ( e = this.originalEvent ) {
				e.preventDefault();
				this.defaultPrevented = true;
			}
		},
		stopPropagation: function(e) {
			if ( e = this.originalEvent ) {
				e.stopPropagation();
				this.propagationStopped = true;
			}
		},

		/* <anyevent_subject */
		setSubject: function(subject) {
			this.subject = subject;
			if ( this.pageXY ) {
				this.subjectXY = this.pageXY;
				if ( this.subject.getPosition ) {
					this.subjectXY = this.subjectXY.subtract(this.subject.getPosition());
				}
			}
		}
		/* anyevent_subject> */
	});
	/* anyevent> */

	/* <event_keys */
	Event.Keys = {"enter": 13, "up": 38, "down": 40, "left": 37, "right": 39, "esc": 27, "space": 32, "backspace": 8, "tab": 9, "delete": 46};
	/* event_keys> */

	/* <event_custom */
	Event.Custom = {
		/* <_event_custom_mousenterleave */
		mouseenter: {
			type: 'mouseover',
			filter: function(e) {
				return e.fromElement != this && !this.contains(e.fromElement);
			}
		},
		mouseleave: {
			type: 'mouseout',
			filter: function(e) {
				return e.toElement != this && !this.contains(e.toElement);
			}
		},
		/* _event_custom_mousenterleave> */

		/* <event_custom_mousewheel */
		mousewheel: {
			type: 'onmousewheel' in W ? 'mousewheel' : 'mousescroll'
		},
		/* event_custom_mousewheel> */

		/* <event_custom_directchange */
		directchange: {
			type: 'keyup',
			filter: function(e) {
				var lastValue = this._dc == null ? this.defaultValue : this._dc,
					currentValue = this.value;
				this._dc = currentValue;
				return lastValue == null || lastValue != currentValue;
			}
		}
		/* event_custom_directchange> */
	};

	/* <_event_custom_mousenterleave */
	'onmouseenter' in html && delete Event.Custom.mouseenter;
	'onmouseleave' in html && delete Event.Custom.mouseleave;
	/* _event_custom_mousenterleave> */
	/* event_custom> */

	/* <native_extend */
	$each([
		window, 
		document, 
		Element,
		/* <native_extend_elements */
		Elements
		/* native_extend_elements> */
	], function(Host) {
		Host.extend = function(methods) {
			$extend([this], methods);
		};
	});
	/* native_extend> */

	/* <eventable */
	function Eventable(subject) {
		this.subject = subject;
		this.time = Date.now();
	}
	$extend(Eventable, {
		/* <eventable_on */
		on: function(eventType, matches, callback) {
			callback || (callback = matches) && (matches = null);
			var bubbles = !!matches;

			var baseType = eventType,
				customEvent = false;
			if ( Event.Custom[eventType] ) {
				customEvent = Event.Custom[eventType];
				customEvent.type && (baseType = customEvent.type);
			}

			function onCallback(e, arg2) {
				e && !(e instanceof AnyEvent) && (e = new AnyEvent(e));

				// Find event subject
				var subject = this;
				if ( e && e.target && matches ) {
					if ( !(subject = e.target.selfOrFirstAncestor(matches)) ) {
						return;
					}
				}

				// Custom event type filter
				if ( customEvent && customEvent.filter ) {
					if ( !customEvent.filter.call(subject, e, arg2) ) {
						return;
					}
				}

				e.subject || e.setSubject(subject);
				return callback.call(subject, e, arg2);
			}

			if ( customEvent && customEvent.before ) {
				if ( customEvent.before.call(this) === false ) {
					return;
				}
			}

			var events = this.$events || (this.$events = {});
			events[eventType] || (events[eventType] = []);
			events[eventType].push({type: baseType, original: callback, callback: onCallback, bubbles: bubbles});

			this.addEventListener(baseType, onCallback, bubbles);
			return this;
		},
		/* eventable_on> */

		/* <eventable_off */
		off: function(eventType, callback) {
			if ( this.$events && this.$events[eventType] ) {
				var events = this.$events[eventType],
					changed = false;
				$each(events, function(listener, i) {
					if ( !callback || callback == listener.original ) {
						changed = true;
						delete events[i];
						this.removeEventListener(listener.type, listener.callback, listener.bubbles);
					}
				}, this);
				changed && (this.$events[eventType] = events.filter(Array.defaultFilterCallback));
			}
			return this;
		},
		/* eventable_off> */

		/* <eventable_fire */
		fire: function(eventType, e, arg2) {
			if ( this.$events && this.$events[eventType] ) {
				e || (e = new AnyEvent(eventType));
				$each(this.$events[eventType], function(listener) {
					listener.callback.call(this, e, arg2);
				}, this);
			}
			return this;
		},
		/* eventable_fire> */

		/* <eventable_globalfire */
		globalFire: function(globalType, localType, originalEvent, arg2) {
			var e = originalEvent ? originalEvent : new AnyEvent(localType),
				eventType = (globalType + '-' + localType).camel();
			e.target = e.subject = this;
			e.type = localType;
			e.globalType = globalType;
			W.fire(eventType, e, arg2);
			return this;
		}
		/* eventable_globalfire> */
	});
	/* eventable> */

	/* <native_eventable */
	$extend([W, D, Element, XMLHttpRequest], Eventable.prototype);
	W.XMLHttpRequestUpload && $extend([XMLHttpRequestUpload], Eventable.prototype);
	/* native_eventable> */

	$extend(Node, {
		/* <element_ancestor */
		firstAncestor: function(selector) {
			var el = this;
			while ( (el = el.parentNode) && el != D ) {
				if ( el.is(selector) ) {
					return el;
				}
			}
		},
		/* element_ancestor> */

		/* <element_siblings */
		getNext: function() {
			if ( this.nextElementSibling !== undefined ) {
				return this.nextElementSibling;
			}

			var sibl = this;
			while ( (sibl = sibl.nextSibling) && sibl.nodeType != 1 );

			return sibl;
		},
		getPrev: function() {
			if ( this.previousElementSibling !== undefined ) {
				return this.previousElementSibling;
			}

			var sibl = this;
			while ( (sibl = sibl.previousSibling) && sibl.nodeType != 1 );

			return sibl;
		},
		/* element_siblings> */

		/* <element_remove */
		remove: function() {
			return this.parentNode.removeChild(this);
		},
		/* element_remove> */

		/* <element_parent */
		getParent: function() {
			return this.parentNode;
		},
		/* element_parent> */

		/* <element_insertafter */
		insertAfter: function(el, ref) {
			var next = ref.nextSibling; // including Text
			if ( next ) {
				return this.insertBefore(el, next);
			}
			return this.appendChild(el);
		},
		/* element_insertafter> */

		/* <element_index */
		nodeIndex: function() {
			return indexOf.call(this.parentNode.childNodes, this);
		}
		/* element_index> */
	});

	/* <document_el */
	$extend(document, {
		el: function(tag, attrs) {
			var el = this.createElement(tag);
			attrs && el.attr(attrs);
			return el;
		}
	});
	/* document_el> */

	Element.attr2method = {
		/* <element_attr2method_html */
		html: function(value) {
			return value == null ? this.getHTML() : this.setHTML(value);
		},
		/* element_attr2method_html> */

		/* <element_attr2method_text */
		text: function(value) {
			return value == null ? this.getText() : this.setText(value);
		}
		/* element_attr2method_text> */
	};

	var EP = Element.prototype;
	$extend(Element, {
		/* <element_is */
		is: EP.matches || EP.webkitMatches || EP.mozMatches || EP.msMatches || EP.oMatches || EP.matchesSelector || EP.webkitMatchesSelector || EP.mozMatchesSelector || EP.msMatchesSelector || EP.oMatchesSelector || function(selector) {
			return $$(selector).contains(this);
		},
		/* element_is> */

		/* <element_value */
		getValue: function() {
			if ( !this.disabled ) {
				if ( this.nodeName == 'SELECT' && this.multiple ) {
					return [].filter.call(this.options, function(option) {
						return option.selected;
					});
				}
				else if ( this.type == 'radio' || this.type == 'checkbox' && !this.checked ) {
					return;
				}
				return this.value;
			}
		},
		/* element_value> */

		/* <element_toquerystring */
		toQueryString: function() {
			var els = this.getElements('input[name], select[name], textarea[name]'),
				query = [];
			els.forEach(function(el) {
				var value = el.getValue();
				if ( value instanceof Array ) {
					value.forEach(function(val) {
						query.push(el.name + '=' + encodeURIComponent(val));
					});
				}
				else if ( value != null ) {
					query.push(el.name + '=' + encodeURIComponent(value));
				}
			});
			return query.join('&');
		},
		/* element_toquerystring> */

		/* <element_ancestor */
		selfOrFirstAncestor: function(selector) {
			return this.is(selector) ? this : this.firstAncestor(selector);
		},
		/* element_ancestor> */

		/* <_element_contains */
		contains: function(child) {
			return this.getElements('*').contains(child);
		},
		/* _element_contains> */

		/* <element_children */
		getChildren: function(selector) {
			return new Elements(this.children || this.childNodes, selector);
		},
		/* element_children> */

		/* <element_firstlast */
		getFirst: function() {
			if ( this.firstElementChild !== undefined ) {
				return this.firstElementChild;
			}

			return this.getChildren().first();
		},
		getLast: function() {
			if ( this.lastElementChild !== undefined ) {
				return this.lastElementChild;
			}

			return this.getChildren().last();
		},
		/* element_firstlast> */

		/* <element_attr */
		attr: function(name, value, prefix) {
			prefix == null && (prefix = '');
			if ( value === undefined ) {
				// Get single attribute
				if ( typeof name == 'string' ) {
					/* <element_attr2method */
					if ( Element.attr2method[prefix + name] ) {
						return Element.attr2method[prefix + name].call(this, value, prefix);
					}
					/* element_attr2method> */

					return this.getAttribute(prefix + name);
				}

				// (un)set multiple attributes
				$each(name, function(value, name) {
					if ( value === null ) {
						this.removeAttribute(prefix + name);
					}
					else {
						/* <element_attr2method */
						if ( Element.attr2method[prefix + name] ) {
							return Element.attr2method[prefix + name].call(this, value, prefix);
						}
						/* element_attr2method> */

						this.setAttribute(prefix + name, value);
					}
				}, this);
			}
			// Unset single attribute
			else if ( value === null ) {
				this.removeAttribute(prefix + name);
			}
			// Set single attribute
			else {
				if ( typeof value == 'function' ) {
					value = value.call(this, this.getAttribute(prefix + name));
				}

				/* <element_attr2method */
				if ( Element.attr2method[prefix + name] ) {
					return Element.attr2method[prefix + name].call(this, value, prefix);
				}
				/* element_attr2method> */

				this.setAttribute(prefix + name, value);
			}

			return this;
		},
		/* element_attr> */

		/* <element_data */
		data: function(name, value) {
			return this.attr(name, value, 'data-');
		},
		/* element_data> */

		/* <element_html */
		getHTML: function() {
			return this.innerHTML;
		},
		setHTML: function(html) {
			this.innerHTML = html;
			return this;
		},
		/* element_html> */

		/* <element_text */
		getText: function() {
			return this.innerText || this.textContent;
		},
		setText: function(text) {
			this.textContent = this.innerText = text;
			return this;
		},
		/* element_text> */

		getElement: function(selector) {
			return this.querySelector(selector);
		},

		/* <elements */
		getElements: function(selector) {
			return $$(this.querySelectorAll(selector));
		},
		/* elements> */

		/* <element_class */
		removeClass: function(token) {
			this.classList.remove(token);
			return this;
		},
		addClass: function(token) {
			this.classList.add(token);
			return this;
		},
		toggleClass: function(token) {
			this.classList.toggle(token);
			return this;
		},
		replaceClass: function(before, after) {
			return this.removeClass(before).addClass(after);
		},
		hasClass: function(token) {
			return this.classList.contains(token);
		},
		/* element_class> */

		/* <element_inject */
		injectBefore: function(ref) {
			ref.parentNode.insertBefore(this, ref);
			return this;
		},
		injectAfter: function(ref) {
			ref.parentNode.insertAfter(this, ref);
			return this;
		},
		inject: function(parent) {
			parent.appendChild(this);
			return this;
		},
		injectTop: function(parent) {
			parent.firstChild ? parent.insertBefore(this, parent.firstChild) : parent.appendChild(this);
			return this;
		},
		/* element_inject> */

		/* <element_append */
		append: function(child) {
			this.appendChild(child);
			return this;
		},
		/* element_append> */

		/* <element_style */
		getStyle: function(property) {
			return getComputedStyle(this).getPropertyValue(property);
		},
		/* element_style> */

		/* <element_css */
		css: function(property, value) {
			if ( value === undefined ) {
				// Get single property
				if ( typeof property == 'string' ) {
					return this.getStyle(property);
				}

				// Set multiple properties
				$each(property, function(value, name) {
					this.style[name] = value;
				}, this);
				return this;
			}

			// Set single property
			this.style[property] = value;
			return this;
		},
		/* element_css> */

		/* <element_show */
		show: function() {
			if ( !cssDisplays[this.nodeName] ) {
				var el = document.el(this.nodeName).inject(this.ownerDocument.body);
				cssDisplays[this.nodeName] = el.getStyle('display');
				el.remove();
			}
			return this.css('display', cssDisplays[this.nodeName]);
		},
		hide: function() {
			return this.css('display', 'none');
		},
		toggle: function() {
			return this.getStyle('display') == 'none' ? this.show() : this.hide();
		},
		/* element_show> */

		/* <element_empty */
		empty: function() {
			try {
				this.innerHTML = '';
			}
			catch (ex) {
				while ( this.firstChild ) {
					this.removeChild(this.firstChild);
				}
			}
			return this;
		},
		/* element_empty> */

		/* <element_index */
		elementIndex: function() {
			return this.parentNode.getChildren().indexOf(this);
		},
		/* element_index> */

		/* <element_position */
		getPosition: function() {
			var bcr = this.getBoundingClientRect();
			return new Coords2D(bcr.left, bcr.top).add(W.getScroll());
		},
		/* element_position> */

		/* <element_scroll */
		getScroll: function() {
			return new Coords2D(this.scrollLeft, this.scrollTop);
		}
		/* element_scroll> */
	});

	$extend(document, {
		getElement: Element.prototype.getElement,
		getElements: Element.prototype.getElements
	});

	/* <windoc_scroll */
	$extend([W, D], {
		getScroll: function() {
			return new Coords2D(
				document.documentElement.scrollLeft || document.body.scrollLeft,
				document.documentElement.scrollTop || document.body.scrollTop
			);
		}
	});
	/* windoc_scroll> */

	/* <domready */
	Event.Custom.ready = {
		before: function() {
			if ( this == document ) {
				domReadyAttached || attachDomReady();
			}
		}
	};

	function onDomReady() {
		var rs = D.readyState;
		if ( !domIsReady && (rs == 'complete' || rs == 'interactive') ) {
			domIsReady = true;
			D.fire('ready');
		}
	}

	function attachDomReady() {
		domReadyAttached = true;

		if ( D.addEventListener ) {
			D.addEventListener('DOMContentLoaded', onDomReady, false);
		}
		else if ( D.attachEvent ) {
			D.attachEvent('onreadystatechange', onDomReady);
		}
	}
	/* domready> */

	function $(id, selector) {
		/* <domready */
		if ( typeof id == 'function' ) {
			if ( domIsReady ) {
				setTimeout(id, 1);
				return D;
			}

			return D.on('ready', id);
		}
		/* domready> */

		// By [id]
		if ( !selector ) {
			return D.getElementById(id);
		}

		// By selector
		return D.getElement(id);
	}

	/* <elements */
	function $$(selector) {
		return $arrayish(selector) ? new Elements(selector) : D.getElements(selector);
	}
	/* elements> */

	/* <xhr */
	function XHR(url, options) {
		options || (options = {});
		options.method = (options.method ? options.method : 'GET').toUpperCase();
		options.async != null || (options.async = true);
		options.send != null || (options.send = true);
		options.data != null || (options.data = null);
		options.url = url;

		var xhr = new XMLHttpRequest;
		xhr.open(options.method, options.url, options.async, options.username, options.password);
		xhr.options = options;
		xhr.on('readystatechange', function(e) {
			if ( this.readyState == 4 ) {
				var success = this.status == 200,
					eventType = success ? 'success' : 'error',
					t = this.responseText;

				try {
					this.responseJSON = (t[0] == '[' || t[0] == '{') && JSON.parse(t);
				}
				catch (ex) {}
				var response = this.responseJSON || this.responseXML || t;

				// Specific events
				this.fire(eventType, e, response);
				this.fire('done', e, response);

				/* <xhr_global */
				// Global events
				this.globalFire('xhr', eventType, e, response);
				this.globalFire('xhr', 'done', e, response);
				/* xhr_global> */
			}
		});
		if ( options.method == 'POST' ) {
			if ( !$is_a(options.data, 'FormData') ) {
				var encoding = options.encoding ? '; charset=' + encoding : '';
				xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded' + encoding);
			}
		}
		if ( options.send ) {
			xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			/* <xhr_global */
			xhr.globalFire('xhr', 'start');
			/* xhr_global> */
			xhr.fire('start');
			xhr.send(options.data);
		}
		return xhr;
	}

	function shortXHR(method) {
		return function(url, data, options) {
			options || (options = {});
			options.method = method;
			options.data = data;
			var xhr = XHR(url, options);
			return xhr;
		};
	}
	/* xhr> */



	// Expose
	/* <ifsetor */
	W.$ifsetor = $ifsetor;
	/* ifsetor> */
	W.$arrayish = $arrayish;
	/* <array */
	W.$array = $array;
	/* array> */
	/* <class */
	W.$class = $class;
	/* class> */
	W.$is_a = $is_a;
	/* <serialize */
	W.$serialize = $serialize;
	/* serialize> */
	W.$each = $each;
	W.$extend = $extend;
	/* <getter */
	W.$getter = $getter;
	/* getter> */

	W.$ = $;

	/* <elements */
	W.$$ = $$;
	W.Elements = Elements;
	/* elements> */

	/* <anyevent */
	W.AnyEvent = AnyEvent;
	/* anyevent> */

	/* <eventable */
	W.Eventable = Eventable;
	/* eventable> */

	/* <coords2d */
	W.Coords2D = Coords2D;
	/* coords2d> */

	/* <xhr */
	W.$.xhr = XHR;
	W.$.get = shortXHR('get');
	W.$.post = shortXHR('post');
	/* xhr> */

	// } catch (ex) { alert(ex); }

})(this, this.document);
