/**
 * XHR HTTP Stream Support
 *
 * This function allows streamed HTTP response data to be read incrementally from the responseText.
 *
 * @returns {string}
 */
XMLHttpRequest.prototype.read = function () {
    if (typeof this.readOffset == 'undefined')
        this.readOffset = 0;
    if (this.response.length <= this.readOffset)
        return null;
    var pos = this.response.substr(this.readOffset).indexOf("\0");
    if (pos < 0)
        return null;
    var len = parseInt('0x' + this.response.substr(this.readOffset, pos));
    var part = this.response.substr(this.readOffset + pos + 1, len);
    if (part.length < len)
        return null;
    this.readOffset += (part.length + pos + 1);
    return part;
};

/**
 * jQuery Ajax Stream Support
 *
 * This jQuery function extends the standard AJAX function to allow progress events for streaming over HTTP.
 *
 * @returns {string}
 */
jQuery.stream = function (url, options) {
    var callbacks = {};
    if (typeof url == 'object') {
        options = url;
        url = options.url;
    }
    var ajax = jQuery.ajax(url, jQuery.extend(options, {
        beforeSend: function (request) {
            request.setRequestHeader("X-Request-Type", "stream");
        },
        xhr: function () {
            var xhr = new XMLHttpRequest();
            xhr.upload.onprogress = function (event) { if (typeof callbacks.uploadProgress == 'function') callbacks.uploadProgress(event); };
            xhr.onprogress = function (event) {
                var response;
                while (response = this.read()) {
                    if (typeof callbacks.progress == 'function')
                        callbacks.progress(response, event.statusText, ajax);
                }
            };
            xhr.onloadend = function (event) {
                var response = this.read();
                if (typeof callbacks.done == 'function')
                    callbacks.done(response, event.statusText, ajax);
            };
            return xhr;
        }
    }));
    ajax.uploadProgress = function (callback) { callbacks.uploadProgress = callback; return this; };
    ajax.progress = function (callback) { callbacks.progress = callback; return this; };
    ajax.done = function (callback) { callbacks.done = callback; return this; };
    return ajax;
};

/**
 * jQuery Remove Event
 *
 * This event will be fired when an element is removed from the DOM using jQuery.remove();
 *
 * To bind: jQuery('#elemnt').on('remove', function(){ 'do the things' });
 */
(function ($) {
    var oldClean = jQuery.cleanData;
    $.cleanData = function (elems) {
        for (var i = 0, elem; (elem = elems[i]) !== undefined; i++)
            $(elem).triggerHandler("remove");
        return oldClean(elems);
    }
})(jQuery);

/**
 * The Hazaar MVC Data binder
 *
 * This is a simple JavaScript/jQuery data bindering function to bind object data to
 * HTML elements with automatic updates on change.
 */
var dataBinder = function (data, name, parent) {
    if (this === window)
        return new dataBinder(data);
    this._init(data, name, parent);
};

var dataBinderArray = function (data, name, parent) {
    if (this === window)
        return new dataBinderArray(data, name, parent);
    this._init(data, name, parent);
};

var dataBinderValue = function (name, value, label, parent) {
    this._parent = parent;
    this._value = null;
    this._label = null;
    this.name = name;
    Object.defineProperties(this, {
        "value": {
            set: function (value) {
                this._value = value;
                this._parent._update(this.name);
            },
            get: function () {
                return this._value;
            }
        },
        "label": {
            set: function (value) {
                this._label = value;
                this._parent._update(this.name);
            },
            get: function () {
                return this._label;
            }
        }
    });
    this.set(value, label);
};

dataBinderValue.prototype.toString = function () {
    return this.label || this.value;
};

dataBinderValue.prototype.set = function (value, label) {
    this._value = value;
    this._label = label;
    this._parent._update(this.name);
    return this;
};

dataBinderValue.prototype.setLabel = function (label) {
    if (typeof label == 'undefined') return this.label;
    this.label = label;
    this._parent._update(this.name);
    return this;
};

dataBinderValue.prototype.setValue = function (value) {
    if (typeof value == 'undefined') return this.value;
    this.value = value;
    this._parent._update(this.name);
    return this;
};

dataBinderValue.prototype.save = function () {
    if (this.label)
        return { "value": this.value, "label": this.label };
    return this.value;
};

dataBinder.prototype._init = function (data, name, parent) {
    this._jquery = jQuery({});
    this._name = name;
    this._parent = parent;
    this._attributes = {};
    this._watchers = {};
    if (Object.keys(data).length > 0) {
        for (var key in data)
            this.add(key, data[key]);
    }
    Object.defineProperty(this, 'length', {
        get: function () {
            return Object.keys(this._attributes).length;
        }
    });
};

dataBinder.prototype.add = function (key, value) {
    var attr_name = this._attr_name(key);
    var trigger_name = this._trigger_name(attr_name);
    if (Array.isArray(value)) {
        this._attributes[key] = new dataBinderArray(value, key, this);
    } else if (typeof value == 'object' && value !== null) {
        this._attributes[key] = new dataBinder(value, key, this);
    } else {
        this._jquery.on(trigger_name, function (event, binder, attr_name, attr_value) {
            binder._update(attr_name, true);
        });
        this._attributes[key] = value;
        this._update(attr_name, false);
    }
    this._defineProperty(trigger_name, key);
};

dataBinder.prototype._trigger_name = function (attr_name) {
    return attr_name.replace(/[\[\]]/g, '_') + ':change';
};

dataBinder.prototype._defineProperty = function (trigger_name, key) {
    var attr_name = this._attr_name(key);
    Object.defineProperty(this, key, {
        configurable: true,
        set: function (value) {
            if (this._attributes[key] === value) return;
            if (Array.isArray(value))
                value = new dataBinderArray(value, key, this);
            else if (!(value instanceof dataBinder
                || value instanceof dataBinderArray
                || value instanceof dataBinderValue))
                value = new dataBinderValue(key, value, null, this);
            this._attributes[key] = value;
            this._jquery.trigger(trigger_name, [this, attr_name, value]);
            this._trigger(attr_name, value);
        },
        get: function () {
            if (!this._attributes[key])
                this._attributes[key] = new dataBinderValue(key, null, null, this);
            return this._attributes[key];
        }
    });
};

dataBinder.prototype.remove = function (key) {
    if (!(key in this._attributes))
        return;
    this._jquery.off(this._attr_name(key) + ':change');
    delete this[key];
    delete this._attributes[key];
};

dataBinder.prototype._attr_name = function (attr_name) {
    if (!this._parent) return attr_name;
    return this._parent._attr_name(this._name) + (typeof attr_name == 'undefined' ? '' : '.' + attr_name);
};

dataBinder.prototype._update = function (attr_name, do_update) {
    var attr_item = this._attributes[attr_name];
    jQuery('[data-bind="' + attr_name + '"]').each(function (index, item) {
        var o = jQuery(item);
        if (o.is("input, textarea, select")) {
            var attr_value = (attr_item ? attr_item.value : null);
            ((o.attr('type') == 'checkbox') ? o.prop('checked', attr_value) : o.val(attr_value));
            if (do_update === true) o.trigger('update');
        } else
            o.html((attr_item ? attr_item.toString() : null));
    });
};

dataBinder.prototype._trigger = function (key, value) {
    if (key in this._watchers) {
        for (x in this._watchers[key])
            this._watchers[key][x][0](key, value, this._watchers[key][x][1]);
    }
};

dataBinder.prototype.resync = function (name) {
    for (x in this._attributes)
        this._jquery.off(this._trigger_name(this._attr_name(x)));
    if (typeof name !== 'undefined') this._name = name;
    for (x in this._attributes) {
        var trigger_name = this._trigger_name(this._attr_name(x));
        this._jquery.on(trigger_name, function (event, binder, attr_name, attr_value) {
            binder._update(attr_name, true);
        });
        this._defineProperty(trigger_name, x);
        if (x in this._attributes) {
            if (this._attributes[x] instanceof dataBinder || this._attributes[x] instanceof dataBinderArray)
                this._attributes[x].resync();
            else this._update(this._attr_name(x), false);
        }
    }
    return this;
};

dataBinder.prototype.save = function () {
    var attrs = jQuery.extend({}, this._attributes);
    for (x in attrs) {
        if (attrs[x] instanceof dataBinder
            || attrs[x] instanceof dataBinderArray
            || attrs[x] instanceof dataBinderValue)
            attrs[x] = attrs[x].save();
    }
    return attrs;
};

dataBinder.prototype.watch = function (key, callback, args) {
    if (!(key in this._watchers))
        this._watchers[key] = [];
    this._watchers[key].push([callback, args]);
};

dataBinder.prototype.unwatch = function (key) {
    if (typeof key == 'undefined')
        this._watchers = {};
    else if (key in this._watchers)
        delete this._watchers[key];
};

dataBinder.prototype.keys = function () {
    return Object.keys(this._attributes);
};

dataBinder.prototype.populate = function (items) {
    for (x in this._attributes) {
        if (!(x in items))
            this.remove(x);
    }
    for (x in items) {
        if (items[x] instanceof dataBinder || items[x] instanceof dataBinderArray || !(x in this._attributes))
            this.add(items[x]);
    }
};

dataBinder.prototype.extend = function (items) {
    for (x in items) {
        if (x in this._attributes) {
            if (this._attributes[x] instanceof dataBinder)
                this[x].extend(items[x]);
            else if (this._attributes[x] instanceof dataBinderArray)
                this[x].populate(items[x]);
            else
                this[x] = items[x];
        } else
            this.add(x, items[x]);
    }
};

dataBinderArray.prototype._init = function (data, name, parent) {
    this._name = name;
    this._parent = parent;
    this._elements = [];
    this.resync();
    if (data.length > 0)
        for (x in data) this.push(data[x]);
    Object.defineProperty(this, 'length', {
        get: function () {
            return this._elements.length;
        }
    });
};

dataBinderArray.prototype._trigger_name = dataBinder.prototype._trigger_name;

dataBinderArray.prototype._attr_name = function (attr_name) {
    if (!this._parent) return attr_name;
    return this._parent._attr_name(this._name) + (typeof attr_name == 'undefined' ? '' : '[' + attr_name + ']');
};

dataBinderArray.prototype.pop = function (element) {
    var index = this._elements.length - 1;
    var element = this._elements[index];
    this.remove(index);
    return element;
};

dataBinderArray.prototype._newitem = function (index) {
    var attr_name = this._attr_name(index);
    var a = this, newitem = jQuery(this._template.html()).attr('data-bind', attr_name);
    newitem.on('remove', function () {
        var index = Array.from(this.parentNode.children).indexOf(this);
        if (index >= 0) a._cleanupItem(index);
    }).find('[data-bind]').each(function (idx, item) {
        var key = item.attributes['data-bind'].value;
        if (idx in a._elements) item.attributes['data-bind'].value = attr_name + '.' + key;
    });
    return newitem;
};

dataBinderArray.prototype._update = function (attr_name, attr_element) {
    var remove = (this.indexOf(attr_element) < 0);
    jQuery('[data-bind="' + attr_name + '"]').each(function (index, item) {
        var o = $(item);
        if (o.is('[data-toggle]')) {
            o.find('[data-bind-value="' + attr_element.value + '"]')
                .toggleClass('active', !remove)
                .children('input[type=checkbox]').prop('checked', !remove);
        } else {
            if (remove)
                o.children().eq(index).remove();
            else if (this._template && this._template.length > 0)
                o.append(this._newitem(key));
        }
    });
};

dataBinderArray.prototype.push = function (element) {
    var key = this._elements.length;
    if (!Object.getOwnPropertyDescriptor(this, key)) {
        var trigger_name = this._trigger_name(this._attr_name(key));
        Object.defineProperty(this, key, {
            set: function (value) {
                if (Array.isArray(value))
                    value = new dataBinderArray(value, key, this);
                else if (typeof element == 'object')
                    value = new dataBinder(value, key, this);
                else
                    value = new dataBinderValue(key, value, null, this);
                this._elements[key] = value;
            },
            get: function () {
                return this._attributes[key];
            }
        });
    }
    if (Array.isArray(element))
        element = new dataBinderArray(element, key, this);
    else if (typeof element == 'object')
        element = new dataBinder(element, key, this);
    this._elements[key] = element;
    this._update(this._attr_name(), element);
    return key;
};

dataBinderArray.prototype.indexOf = function (searchString) {
    return this._elements.indexOf(searchString);
};

dataBinderArray.prototype.remove = function (index) {
    if (typeof index == 'string')
        index = this.indexOf(index);
    if (index < 0)
        return;
    var element = this._elements[index];
    this._cleanupItem(index);
    this._update(this._attr_name(), element);
    return element;
};

dataBinderArray.prototype.save = function () {
    var elems = this._elements.slice();
    for (x in elems) {
        if (elems[x] instanceof dataBinder || elems[x] instanceof dataBinderArray)
            elems[x] = elems[x].save();
    }
    return elems;
};

dataBinderArray.prototype.resync = function () {
    if (!this._template || this._template.length == 0) {
        this._template = jQuery('[data-bind="' + this._attr_name() + '"]').children('template');
        if (this._template.length > 0)
            this._template.detach();
    }
    if (this._template && this._template.length > 0) {
        var parent = jQuery('[data-bind="' + this._attr_name() + '"]');
        for (x in this._elements) {
            var attr_name = this._attr_name(x);
            var item = parent.children('[data-bind="' + attr_name + '"]');
            if (item.length == 0)
                parent.append(this._newitem(x));
            if (this._elements[x] instanceof dataBinder || this._elements[x] instanceof dataBinderArray)
                this._elements[x].resync();
        }

    }
};

dataBinderArray.prototype._cleanupItem = function (index) {
    if (!index in this._elements) return;
    var reg = new RegExp("(" + this._attr_name() + ")\\[(\\d+)\\]");
    for (var i = (index + 1); i < this._elements.length; i++) {
        var new_i = i - 1;
        jQuery('[data-bind^="' + this._attr_name(i) + '"]').each(function (index, item) {
            if (!('data-toggle' in this.attributes))
                this.attributes['data-bind'].value = this.attributes['data-bind'].value.replace(reg, '$1[' + new_i + ']');
        });
        if (i in this._elements && (this._elements[i] instanceof dataBinder || this._elements[i] instanceof dataBinderArray))
            this._elements[i].resync(new_i);
    }
    var elem = this._elements.splice(index, 1);
    return elem;
};

dataBinderArray.prototype.populate = function (elements) {
    if (!Array.isArray(elements))
        elements = Object.values(elements);
    for (x in elements) {
        var removed_items = this._elements.filter(function (e) {
            return (this.indexOf(e) < 0);
        }, elements);
        for (x in removed_items)
            this.remove(removed_items[x]);
        if (elements[x] instanceof dataBinder || elements[x] instanceof dataBinderArray || (this._elements.indexOf(elements[x]) < 0))
            this.push(elements[x]);
    }
}