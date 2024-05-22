/**
 * jQuery Ajax Stream Support
 *
 * This jQuery function extends the standard AJAX function to allow progress events for streaming over HTTP.
 *
 * @param {string} url The URL
 * @param {object} options Stream options
 * @return {object} The object itself
 */
jQuery.stream = function (url, options) {
    let callbacks = {};
    if (typeof url === 'object') {
        options = url;
        url = options.url;
    }
    let worker = {
        readOffset: 0,
        xhr: null,
        read: function () {
            if (this.xhr.response.length <= this.readOffset)
                return null;
            let pos = this.xhr.response.substr(this.readOffset).indexOf("\0");
            if (pos <= 0)
                return null;
            let len = parseInt('0x' + this.xhr.response.substr(this.readOffset, pos));
            let type = this.xhr.response.substr(this.readOffset + pos + 1, 1);
            let part = this.xhr.response.substr(this.readOffset + pos + 2, len);
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
            let type = this.xhr.response.substr(this.readOffset + 1, 1);
            let part = this.xhr.response.substr(this.readOffset + 2);
            if (type === 'a' || type === 'e') part = JSON.parse(part);
            return part;
        }
    };
    let ajax = jQuery.ajax(url, jQuery.extend(options, {
        beforeSend: function (request) {
            request.setRequestHeader("X-Request-Type", "stream");
        },
        xhr: function () {
            worker.xhr = new XMLHttpRequest();
            worker.xhr.upload.onprogress = function (event) {
                if (typeof callbacks.uploadProgress === 'function') callbacks.uploadProgress(event);
            };
            worker.xhr.onprogress = function (event) {
                let response;
                while ((response = worker.read()) !== null) {
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
