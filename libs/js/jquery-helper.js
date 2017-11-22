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
    if (pos <= 0)
        return null;
    var len = parseInt('0x' + this.response.substr(this.readOffset, pos));
    var type = this.response.substr(this.readOffset + pos + 1, 1);
    var part = this.response.substr(this.readOffset + pos + 2, len);
    if (part.length < len)
        return null;
    this.readOffset += (part.length + pos + 2);
    if (type == 'a') part = JSON.parse(part);
    return part;
};

XMLHttpRequest.prototype.lastType = function () {
    return this.response.substr(this.readOffset + 1, 1);
};

XMLHttpRequest.prototype.last = function () {
    var type = this.response.substr(this.readOffset + 1, 1);
    var part = this.response.substr(this.readOffset + 2);
    if (type == 'a' || type == 'e') part = JSON.parse(part);
    return part;
}

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
            xhr.upload.onprogress = function (event) {
                if (typeof callbacks.uploadProgress == 'function') callbacks.uploadProgress(event);
            };
            xhr.onprogress = function (event) {
                var response;
                while (response = this.read()) {
                    if (typeof callbacks.progress == 'function')
                        callbacks.progress(response, event.statusText, ajax);
                }
            };
            xhr.onloadend = function (event) {
                if (this.lastType() == 'e') {
                    ajax.responseJSON = this.last();
                    if (typeof callbacks.error == 'function')
                        callbacks.error(ajax, 'error', 'Internal Error');
                } else if (typeof callbacks.done == 'function')
                    callbacks.done(this.last(), event.statusText, ajax);
            };
            return xhr;
        }
    }));
    ajax.uploadProgress = function (cb) { callbacks.uploadProgress = cb; return this; };
    ajax.progress = function (cb) { callbacks.progress = cb; return this; };
    ajax.done = function (cb) { callbacks.done = cb; return this; };
    ajax.error = function (cb) { callbacks.error = cb; return this; };
    ajax.fail(function (xhr, status, statusText) {
        if (typeof callbacks.error == 'function')
            callbacks.error(xhr, status, statusText);
    });
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
    this._name = name;
    this._value = parent.__nullify(value);
    this._label = label;
    this._parent = parent;
    Object.defineProperties(this, {
        "value": {
            set: function (value) {
                this._value = this._parent.__nullify(value);
                this._parent._update(this._name, true);
                this._parent._trigger(this._name, this._value);
            },
            get: function () {
                return this._value;
            }
        },
        "label": {
            set: function (value) {
                this._label = value;
                this._parent._update(this._name, false);
            },
            get: function () {
                return this._label;
            }
        }
    });
};

dataBinderValue.prototype.toString = function () {
    return this.label || this.value;
};

dataBinderValue.prototype.set = function (value, label) {
    this._value = this._parent.__nullify(value);
    this._label = label;
    this._parent._update(this._name, true);
    this._parent._trigger(this._name, this._value);
    return this;
};

dataBinderValue.prototype.save = function (no_label) {
    if (this.value && this.label && !no_label)
        return { "__hz_value": this.value, "__hz_label": this.label };
    return this.value;
};

dataBinder.prototype._init = function (data, name, parent) {
    this._jquery = jQuery({});
    this._name = name;
    this._parent = parent;
    this._attributes = {};
    this._watchers = {};
    if (Object.keys(data).length > 0)
        for (var key in data) this.add(key, data[key]);
    Object.defineProperty(this, 'length', {
        get: function () {
            return Object.keys(this._attributes).length;
        }
    });
};

dataBinder.prototype.__nullify = function (value) {
    if (typeof value === 'string' && value === '')
        value = null;
    return value;
};

dataBinder.prototype.__convert_type = function (key, value) {
    value = this.__nullify(value);
    if (Array.isArray(value))
        value = new dataBinderArray(value, key, this);
    else if (value !== null && typeof value == 'object' && '__hz_value' in value && '__hz_label' in value) {
        if (typeof value.__hz_value == 'string' && value.__hz_value == '') value = null;
        else value = new dataBinderValue(key, value.__hz_value, value.__hz_label, this);
    } else if (value !== null && !(value instanceof dataBinder
        || value instanceof dataBinderArray
        || value instanceof dataBinderValue)) {
        if (typeof value == 'object')
            value = new dataBinder(value, key, this);
        else
            value = new dataBinderValue(key, value, null, this);
    }
    return value;
};

dataBinder.prototype.add = function (key, value) {
    var attr_name = this._attr_name(key);
    var trigger_name = this._trigger_name(attr_name);
    this._attributes[key] = this.__convert_type(key, value);
    this._update(attr_name, false);
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
            var value = this.__convert_type(key, value);
            if ((this._attributes[key] instanceof dataBinderValue ? this._attributes[key].value : this._attributes[key]) == (value instanceof dataBinderArray ? value.value : value)) return;
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
            if (o.attr('type') == 'checkbox')
                o.prop('checked', attr_value);
            else if (o.attr('data-bind-label') == 'true')
                o.val((attr_item ? attr_item.label : null));
            else if (o.is("select")) {
                if (o.find('option[value="' + attr_value + '"]').length > 0) o.val(attr_value);
            } else
                o.val(attr_value);
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

dataBinder.prototype.save = function (no_label) {
    var attrs = jQuery.extend({}, this._attributes);
    for (x in attrs) {
        if (attrs[x] instanceof dataBinder
            || attrs[x] instanceof dataBinderArray
            || attrs[x] instanceof dataBinderValue)
            attrs[x] = attrs[x].save(no_label);
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
    if (!parent) throw "dataBinderArray requires a parent!";
    this._name = name;
    this._parent = parent;
    this._elements = [];
    this.resync();
    if (Array.isArray(data) && data.length > 0)
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

dataBinderArray.prototype._newitem = function (index, element) {
    var attr_name = this._attr_name(index);
    var a = this, newitem = jQuery(this._template.html()).attr('data-bind', attr_name);
    newitem.on('remove', function () {
        var index = Array.from(this.parentNode.children).indexOf(this);
        if (index >= 0) a._cleanupItem(index);
    }).find('[data-bind]').each(function (idx, item) {
        var key = item.attributes['data-bind'].value;
        item.innerHTML = element[key];
        item.attributes['data-bind'].value = attr_name + '.' + key;
    });
    return newitem;
};

dataBinderArray.prototype.__convert_type = function (key, value) {
    return this._parent.__convert_type(key, value);
};

dataBinderArray.prototype._update = function (attr_name, attr_element) {
    var remove = (this.indexOf(attr_element) < 0);
    jQuery('[data-bind="' + attr_name + '"]').each(function (index, item) {
        var o = $(item);
        if (o.is('[data-toggle]')) {
            o.find('[data-bind-value="' + attr_element.value + '"]')
                .toggleClass('active', !remove)
                .children('input[type=checkbox]').prop('checked', !remove);
        }
    });
};

dataBinderArray.prototype.push = function (element) {
    var key = this._elements.length;
    if (!Object.getOwnPropertyDescriptor(this, key)) {
        var trigger_name = this._trigger_name(this._attr_name(key));
        Object.defineProperty(this, key, {
            set: function (value) {
                this._elements[key] = this.__convert_type(key, value);
            },
            get: function () {
                return this._elements[key];
            }
        });
    }
    this._elements[key] = this.__convert_type(key, element);
    if (this._elements[key] instanceof dataBinder)
        jQuery('[data-bind="' + this._attr_name() + '"]').append(this._newitem(key, this._elements[key]));
    else
        this._update(this._attr_name(), this._elements[key]);
    return key;
};

dataBinderArray.prototype.indexOf = function (searchString) {
    if (searchString instanceof dataBinderValue) searchString = searchString.value;
    for (i in this._elements) if (this._elements[i].value == searchString) return parseInt(i);
    return -1;
};

dataBinderArray.prototype.remove = function (index) {
    if (index instanceof dataBinderValue) index = index.value;
    if (typeof index == 'string') index = this.indexOf(index);
    if (index < 0 || typeof index == 'undefined') return;
    var element = this._elements[index];
    if (element instanceof dataBinder)
        jQuery('[data-bind="' + this._attr_name() + '"]').children().eq(index).remove();
    else {
        this._cleanupItem(index);
        this._update(this._attr_name(), element);
    }
    return element;
};

dataBinderArray.prototype.save = function (no_label) {
    var elems = this._elements.slice();
    for (x in elems) {
        if (elems[x] instanceof dataBinder
            || elems[x] instanceof dataBinderArray
            || elems[x] instanceof dataBinderValue)
            elems[x] = elems[x].save(no_label);
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
                parent.append(this._newitem(x, this._elements[x]));
            if (this._elements[x] instanceof dataBinder || this._elements[x] instanceof dataBinderArray)
                this._elements[x].resync();
        }

    }
};

dataBinderArray.prototype._cleanupItem = function (index) {
    if (!index in this._elements) return;
    var attr_name = this._attr_name();
    var reg = new RegExp("(" + attr_name + ")\\[(\\d+)\\]");
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
};

dataBinderArray.prototype.filter = function (cb, saved) {
    var list = [];
    for (x in this.elements) {
        var value = (this.elements[x] instanceof dataBinderValue ? this.elements[x].value : this.elements[x]);
        if (cb(value)) list.push(this.elements[x]);
    }
    return list;
};
