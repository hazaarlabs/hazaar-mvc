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
 * The Hazaar MVC Data binder
 *
 * This is a simple JavaScript/jQuery data bindering function to bind object data to
 * HTML elements with automatic updates on change.
 */
var dataBinder = function (data, name, parent) {
    if (this == window)
        throw 'DataBinder is not a new object!';
    this._jquery = jQuery({});
    this._name = name;
    this._parent = parent;
    this._attr_name = function (attr_name) {
        if (this._parent)
            attr_name = this._parent._attr_name(this._name + '.' + attr_name);
        return attr_name;
    }
    this._defineProperty = function (object, key) {
        Object.defineProperty(object, key, {
            set: function (value) {
                var attr_name = this._binder._attr_name(key);
                this._attributes[key] = value;
                this._binder._jquery.trigger(attr_name + ':change', [this._binder, attr_name, value]);
            },
            get: function () {
                return this._attributes[key];
            }
        });
    }
    this._update = function (attr_name, attr_value) {
        jQuery('[data-bind="' + attr_name + '"]').each(function () {
            var o = jQuery(this);
            if (o.is("input, textarea, select"))
                o.val(attr_value);
            else
                o.html(attr_value);
        });
    }
    var object = {
        _binder: this,
        _attributes: data,
        save: function () {
            return $.extend(true, {}, this._attributes);
        }
    }
    for (var key in data) {
        if (Array.isArray(data[key])) {
            var array_len = data[key].length;
            object[key] = [];
            for (var i = 0; i < array_len; i++)
                object[key][i] = new dataBinder(data[key][i], key + '[' + i + ']', this);
        } else if (typeof data[key] == 'object' && data[key] != null) {
            object[key] = new dataBinder(data[key], key, this);
        } else {
            this._jquery.on(this._attr_name(key) + ':change', function (event, binder, attr_name, attr_value) {
                binder._update(attr_name, attr_value);
            });
            this._defineProperty(object, key);
            this._update(this._attr_name(key), data[key]);
        }
    }
    return object;
};