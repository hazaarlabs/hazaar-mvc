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
$.stream = function (url, options) {
    var callbacks = {};
    if (typeof url == 'object') {
        options = url;
        url = options.url;
    }
    var ajax = $.ajax(url, $.extend(options, {
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
 * To bind: $('#elemnt').on('remove', function(){ 'do the things' });
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
}

var dataBinderArray = function (data, name) {
    if (this === window)
        return new dataBinderArray(data, name);
    this._init(data, name);
}

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
    return this._defineProperty(key, value);
};

dataBinder.prototype.remove = function (key) {
    if (!(key in this._attributes))
        return;
    this._jquery.off(this._attr_name(key) + ':change');
    delete this[key];
}

dataBinder.prototype._attr_name = function (attr_name) {
    if (this._parent)
        attr_name = this._parent._attr_name(this._name + '.' + attr_name);
    return attr_name;
}

dataBinder.prototype._defineProperty = function (key, value) {
    if (Array.isArray(value)) {
        value = new dataBinderArray(value, key);
    } else if (typeof value == 'object' && value !== null) {
        value = new dataBinder(value, key, this);
    } else {
        var attr_name = this._attr_name(key);
        this._jquery.on(attr_name + ':change', function (event, binder, attr_name, attr_value) {
            binder._update(attr_name, attr_value);
        });
        this._update(attr_name, value);
    }
    this._attributes[key] = value;
    Object.defineProperty(this, key, {
        set: function (value) {
            var attr_name = this._attr_name(key);
            this._attributes[key] = value;
            this._jquery.trigger(attr_name + ':change', [this, attr_name, value]);
            this._trigger(attr_name, value);
        },
        get: function () {
            return this._attributes[key];
        }
    });
}

dataBinder.prototype._update = function (attr_name, attr_value) {
    jQuery('[data-bind="' + attr_name + '"]').each(function () {
        var o = jQuery(this);
        if (o.is("input, textarea, select"))
            (o.attr('type') == 'checkbox') ? o.prop('checked', attr_value) : o.val(attr_value);
        else
            o.html(attr_value);
    });
}

dataBinder.prototype._trigger = function (key, value) {
    if (key in this._watchers) {
        for (x in this._watchers[key])
            this._watchers[key][x][0](key, value, this._watchers[key][x][1]);
    }
};

dataBinder.prototype.save = function () {
    return Object.assign(this._attributes);
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
}

dataBinderArray.prototype._elements = [];

dataBinderArray.prototype._init = function (data, name) {
    this._name = name;
    this._template = jQuery('[data-bind="' + name + '"]').children('template');
    if (this._template.length > 0)
        this._template.detach();
    if (data.length > 0)
        for (x in data) this.push(data[x]);
    Object.defineProperty(this, 'length', {
        get: function () {
            return this._elements.length;
        }
    });
}

dataBinderArray.prototype._attr_name = dataBinder.prototype._attr_name;

dataBinderArray.prototype.pop = function (element) {
    var index = this._elements.length - 1;
    var element = this._elements[index];
    this.remove(index);
    return element;
}

dataBinderArray.prototype.push = function (element) {
    var key = this._elements.length, a = this;
    var attr_name = this._name + '[' + key + ']';
    this._elements[key] = new dataBinder(element, attr_name, this);
    var newitem;
    if (this._template.length > 0) {
        newitem = $(this._template.html()).attr('data-bind', this._name + '[' + key + ']');
        newitem.on('remove', function () {
            var index = Array.from(this.parentNode.children).indexOf(this);
            if (index >= 0) a._cleanupItem(index);
        }).children().each(function (index, item) {
            if (!item.attributes['data-bind']) return;
            var key = item.attributes['data-bind'].value;
            if (key in element) item.innerHTML = element[key];
            item.attributes['data-bind'].value = attr_name + '.' + key;
        });
    }
    jQuery('[data-bind="' + this._name + '"]').append(newitem);
    if (!Object.getOwnPropertyDescriptor(this, key)) {
        Object.defineProperty(this, key, {
            set: function (value) {
                var attr_name = this._attr_name(key);
                this._elements[key] = value;
                this._jquery.trigger(attr_name + ':change', [this, attr_name, value]);
                this._trigger(attr_name, value);
            },
            get: function () {
                return this._elements[key];
            }
        });
    }
    this.length = this._elements.length;
    return key;
}

dataBinderArray.prototype.remove = function (index) {
    jQuery('[data-bind="' + this._name + '"]').children().eq(index).remove();
    return this._cleanupItem(index);
}

dataBinderArray.prototype.save = function () {
    var elements = [];
    for (x in this._elements) {
        var e = this._elements[x];
        if (e instanceof dataBinder)
            e = e.save();
        elements.push(e);
    }
    return elements;
}

dataBinderArray.prototype._cleanupItem = function (index) {
    if (!index in this._elements) return;
    var reg = new RegExp("(" + this._name + ")\\[(\\d+)\\]");
    for (var i = (index + 1); i < this._elements.length; i++) {
        $('[data-bind^="' + this._name + '[' + i + ']"]').each(function (index, item) {
            this.attributes['data-bind'].value = this.attributes['data-bind'].value.replace(reg, '$1[' + (i - 1) + ']');
        });
    }
    return this._elements.splice(index, 1);
}