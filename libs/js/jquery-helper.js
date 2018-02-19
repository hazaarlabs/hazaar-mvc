/**
 * jQuery Ajax Stream Support
 *
 * This jQuery function extends the standard AJAX function to allow progress events for streaming over HTTP.
 *
 * @param {string} url The URL
 * @param {object} options Stream options
 * @returns {object} The object itself
 */
jQuery.stream = function (url, options) {
    var callbacks = {};
    if (typeof url === 'object') {
        options = url;
        url = options.url;
    }
    var worker = {
        readOffset: 0,
        xhr: null,
        read: function () {
            if (this.xhr.response.length <= this.readOffset)
                return null;
            var pos = this.xhr.response.substr(this.readOffset).indexOf("\0");
            if (pos <= 0)
                return null;
            var len = parseInt('0x' + this.xhr.response.substr(this.readOffset, pos));
            var type = this.xhr.response.substr(this.readOffset + pos + 1, 1);
            var part = this.xhr.response.substr(this.readOffset + pos + 2, len);
            if (part.length < len)
                return null;
            this.readOffset += part.length + pos + 2;
            if (type === 'a') part = JSON.parse(part);
            return part;
        },
        lastType: function (xhr) {
            return this.xhr.response.substr(this.readOffset + 1, 1);
        },
        last: function (xhr) {
            var type = this.xhr.response.substr(this.readOffset + 1, 1);
            var part = this.xhr.response.substr(this.readOffset + 2);
            if (type === 'a' || type === 'e') part = JSON.parse(part);
            return part;
        }
    };
    var ajax = jQuery.ajax(url, jQuery.extend(options, {
        beforeSend: function (request) {
            request.setRequestHeader("X-Request-Type", "stream");
        },
        xhr: function () {
            worker.xhr = new XMLHttpRequest();
            worker.xhr.upload.onprogress = function (event) {
                if (typeof callbacks.uploadProgress === 'function') callbacks.uploadProgress(event);
            };
            worker.xhr.onprogress = function (event) {
                var response;
                while ((response = worker.read())) {
                    if (typeof callbacks.progress === 'function')
                        callbacks.progress(response, event.statusText, ajax);
                }
            };
            worker.xhr.onloadend = function (event) {
                if (worker.lastType() === 'e') {
                    ajax.responseJSON = worker.last();
                    if (typeof callbacks.error === 'function')
                        callbacks.error(ajax, 'error', 'Internal Error');
                } else if (typeof callbacks.done === 'function')
                    callbacks.done(worker.last(), event.statusText, ajax);
            };
            return worker.xhr;
        }
    }));
    ajax.uploadProgress = function (cb) { callbacks.uploadProgress = cb; return this; };
    ajax.progress = function (cb) { callbacks.progress = cb; return this; };
    ajax.done = function (cb) { callbacks.done = cb; return this; };
    ajax.error = function (cb) { callbacks.error = cb; return this; };
    ajax.fail(function (xhr, status, statusText) {
        if (typeof callbacks.error === 'function')
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
    };
})(jQuery);

/**
 * The Hazaar MVC Data binder
 *
 * This is a simple JavaScript/jQuery data bindering function to bind object data to
 * HTML elements with automatic updates on change.
 * 
 * @param {object} data Object data
 * @param {string} name The name of the object (used internally for recursion)
 * @param {object} parent Parent object reference (used internally for recursion)
 * @returns {object} A new dataBinder object.
 */
var dataBinder = function (data, name, parent) {
    if (this === window)
        return new dataBinder(data);
    this._init(data, name, parent);
};

var dataBinderArray = function (data, name, parent) {
    if (this === window) return new dataBinderArray(data, name, parent);
    this._init(data, name, parent);
};

var dataBinderValue = function (name, value, label, parent) {
    this._name = name;
    this._value = parent.__nullify(value);
    this._label = label;
    this._other = null;
    this._parent = parent;
    Object.defineProperties(this, {
        "value": {
            set: function (value) {
                this._value = this._parent.__nullify(value);
                this._other = null;
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
        },
        "other": {
            set: function (value) {
                this._other = value;
                this._parent._update(this._name, false);
            },
            get: function () {
                return this._other;
            }
        }
    });
};

dataBinderValue.prototype.toString = function () {
    return this.label || this.value || this.other;
};

dataBinderValue.prototype.set = function (value, label) {
    this._value = this._parent.__nullify(value);
    this._label = label;
    this._parent._update(this._name, true);
    this._parent._trigger(this._name, this._value);
    return this;
};

dataBinderValue.prototype.save = function (no_label) {
    if (((this.value && this.label) || (this.value === null && this.other)) && no_label !== true)
        return { "__hz_value": this.value, "__hz_label": this.label, "__hz_other": this.other };
    return this.value;
};

dataBinder.prototype._init = function (data, name, parent) {
    this._jquery = jQuery({});
    this._name = name;
    this._parent = parent;
    this._attributes = {};
    this._watchers = {};
    this._watchID = 0;
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

dataBinder.prototype.__convert_type = function (key, value, parent) {
    if (typeof parent === 'undefined') parent = this;
    value = this.__nullify(value);
    if (Array.isArray(value))
        value = new dataBinderArray(value, key, parent);
    else if (value !== null && typeof value === 'object' && '__hz_value' in value) {
        if (typeof value.__hz_value === 'string' && value.__hz_value === '') value = null;
        else {
            var dba = new dataBinderValue(key, value.__hz_value, value.__hz_label, parent);
            if ('__hz_other' in value) dba.other = value.__hz_other;
            value = dba;
        }
    } else if (value !== null && !(value instanceof dataBinder
        || value instanceof dataBinderArray
        || value instanceof dataBinderValue)) {
        if (typeof value === 'object')
            value = new dataBinder(value, key, parent);
        else
            value = new dataBinderValue(key, value, null, parent);
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
            value = this.__convert_type(key, value);
            if (value === null && this._attributes[key] && this._attributes[key].other) this._attributes[key].other = null;
            else if ((this._attributes[key] instanceof dataBinderValue ? this._attributes[key].value : this._attributes[key]) === (value instanceof dataBinderValue ? value.value : value)) return;
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
    return this._parent._attr_name(this._name) + (typeof attr_name === 'undefined' ? '' : '.' + attr_name);
};

dataBinder.prototype._update = function (attr_name, do_update) {
    var attr_item = null;
    if ((pos = attr_name.lastIndexOf('.')) > 0) attr_item = this._attributes[attr_name.substr(pos + 1)];
    else attr_item = this._attributes[attr_name];
    jQuery('[data-bind="' + attr_name + '"]').each(function (index, item) {
        var o = jQuery(item);
        if (o.is("input, textarea, select")) {
            var attr_value = attr_item ? attr_item.value : null;
            if (o.attr('type') === 'checkbox')
                o.prop('checked', attr_value);
            else if (o.attr('data-bind-label') === 'true')
                o.val(attr_item ? attr_item.label : null);
            else if (o.attr('data-bind-other') === 'true')
                o.val(attr_item ? attr_item.other : null);
            else if (o.is("select")) {
                if (o.find('option[value="' + (attr_value === null ? '' : attr_value) + '"]').length > 0) o.val(attr_value);
            } else
                o.val(attr_value);
            if (do_update === true) o.trigger('update', [attr_name, attr_value]);
        } else
            o.html(attr_item ? attr_item.toString() : 'none');
    });
};

dataBinder.prototype._trigger = function (key, value) {
    if (key in this._watchers) {
        for (let x in this._watchers[key])
            this._watchers[key][x][0](key, value, this._watchers[key][x][1]);
    }
};

dataBinder.prototype.resync = function (name) {
    for (let x in this._attributes)
        this._jquery.off(this._trigger_name(this._attr_name(x)));
    if (typeof name !== 'undefined') this._name = name;
    for (let x in this._attributes) {
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
    for (let x in attrs) {
        if (attrs[x] instanceof dataBinder
            || attrs[x] instanceof dataBinderArray
            || attrs[x] instanceof dataBinderValue)
            attrs[x] = attrs[x].save(no_label);
    }
    return attrs;
};

dataBinder.prototype.watch = function (key, callback, args) {
    if (!(key in this._watchers))
        this._watchers[key] = {};
    var id = "" + this._watchID++;
    this._watchers[key][id] = [callback, args];
    return id;
};

dataBinder.prototype.unwatch = function (key, id) {
    if (typeof key === 'undefined') {
        this._watchers = {};
        return;
    }
    if (!(key in this._watchers))
        return;
    if (typeof id !== 'undefined') {
        if (id in this._watchers[key])
            delete this._watchers[key][id];
    } else delete this._watchers[key];
};

dataBinder.prototype.keys = function () {
    return Object.keys(this._attributes);
};

dataBinder.prototype.populate = function (items) {
    for (let x in this._attributes) {
        if (!(x in items))
            this.remove(x);
    }
    for (let x in items) {
        if (items[x] instanceof dataBinder || items[x] instanceof dataBinderArray || !(x in this._attributes))
            this.add(items[x]);
    }
};

dataBinder.prototype.extend = function (items) {
    for (let x in items) {
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

dataBinder.prototype.get = function (key) {
    if (key in this._attributes)
        return this._attributes[key];
};

dataBinderArray.prototype._init = function (data, name, parent) {
    if (!parent) throw "dataBinderArray requires a parent!";
    this._name = name;
    this._parent = parent;
    this._elements = [];
    this._template = null;
    this._watchers = [];
    this.resync();
    if (Array.isArray(data) && data.length > 0)
        for (let x in data) this.push(data[x]);
    Object.defineProperty(this, 'length', {
        get: function () {
            return this._elements.length;
        }
    });
};

dataBinderArray.prototype._trigger_name = dataBinder.prototype._trigger_name;

dataBinderArray.prototype._attr_name = function (attr_name) {
    if (!this._parent) return attr_name;
    return this._parent._attr_name(this._name) + (typeof attr_name === 'undefined' ? '' : '[' + attr_name + ']');
};

dataBinderArray.prototype.pop = function () {
    var index = this._elements.length - 1;
    var element = this._elements[index];
    this.remove(index);
    return element;
};

dataBinderArray.prototype._newitem = function (index, element) {
    var attr_name = this._attr_name(index), a = this;
    var newitem = (this._template.prop('tagName') === 'TEMPLATE')
        ? jQuery(this._template.html()).attr('data-bind', attr_name)
        : this._template.clone(true).attr('data-bind', attr_name);
    newitem.on('remove', function () {
        var index = Array.from(this.parentNode.children).indexOf(this);
        if (index >= 0) a._cleanupItem(index);
    }).find('[data-bind]').each(function (idx, item) {
        var key = item.attributes['data-bind'].value;
        item.attributes['data-bind'].value = attr_name + '.' + key;
    });
    if (this._watchers.length > 0) for (let x in this._watchers) this._watchers[x](newitem);
    return newitem;
};

dataBinderArray.prototype.__convert_type = function (key, value) {
    return this._parent.__convert_type(key, value, this);
};

dataBinderArray.prototype._update = function (attr_name, attr_element) {
    var remove = this.indexOf(attr_element) < 0;
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
    if (this._elements[key] instanceof dataBinder) {
        jQuery('[data-bind="' + this._attr_name() + '"]').append(this._newitem(key, this._elements[key]));
        this.resync();
    } else this._update(this._attr_name(), this._elements[key]);
    return key;
};

dataBinderArray.prototype.indexOf = function (searchString) {
    if (searchString instanceof dataBinderValue) searchString = searchString.value;
    for (let i in this._elements) if (this._elements[i].value == searchString) return parseInt(i);
    return -1;
};

dataBinderArray.prototype.remove = function (index) {
    if (index instanceof dataBinderValue) index = index.value;
    if (typeof index === 'string') index = this.indexOf(index);
    if (index < 0 || typeof index === 'undefined') return;
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
    for (let x in elems) {
        if (elems[x] instanceof dataBinder
            || elems[x] instanceof dataBinderArray
            || elems[x] instanceof dataBinderValue)
            elems[x] = elems[x].save(no_label);
    }
    return elems;
};

dataBinderArray.prototype.resync = function () {
    if (!this._template || this._template.length === 0) {
        var host = jQuery('[data-bind="' + this._attr_name() + '"]');
        if (host.attr('data-bind-template') === 'o') {
            this._template = host.data('template');
        } else {
            this._template = host.children('template');
            if (this._template.length > 0) this._template.detach();
        }
    }
    if (this._template && this._template.length > 0) {
        var parent = jQuery('[data-bind="' + this._attr_name() + '"]');
        for (let x in this._elements) {
            var attr_name = this._attr_name(x);
            var item = parent.children('[data-bind="' + attr_name + '"]');
            if (item.length === 0)
                parent.append(this._newitem(x, this._elements[x]));
            if (this._elements[x] instanceof dataBinder || this._elements[x] instanceof dataBinderArray)
                this._elements[x].resync();
        }
    }
};

dataBinderArray.prototype._cleanupItem = function (index) {
    if (!(index in this._elements)) return;
    var attr_name = this._attr_name();
    var reg = new RegExp("(" + attr_name + ")\\[(\\d+)\\]");
    for (var i = index + 1; i < this._elements.length; i++) {
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
    for (let x in elements) {
        var removed_items = this._elements.filter(function (e) {
            return this.indexOf(e) < 0;
        }, elements);
        for (let x in removed_items)
            this.remove(removed_items[x]);
        if (elements[x] instanceof dataBinder || elements[x] instanceof dataBinderArray || this._elements.indexOf(elements[x]) < 0)
            this.push(elements[x]);
    }
};

dataBinderArray.prototype.filter = function (cb, saved) {
    var list = [];
    for (let x in this.elements) {
        var value = this.elements[x] instanceof dataBinderValue ? this.elements[x].value : this.elements[x];
        if (cb(value)) list.push(this.elements[x]);
    }
    return list;
};

dataBinderArray.prototype.watch = function (cb) {
    if (typeof cb === 'function') this._watchers.push(cb);
}