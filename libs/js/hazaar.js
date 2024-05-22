/**
 * Represents a helper class for HazaarJS.
 * @constructor
 * @param {Object} options - The options for the HazaarJSHelper.
 * @param {string} options.url - The base URL for the HazaarJSHelper.
 * @param {Object} options.data - The data object for the HazaarJSHelper.
 * @param {boolean} [options.rewrite=true] - Indicates whether URL rewriting is enabled or not.
 */
function HazaarJSHelper(options) {
    this.extend = function () {
        var target = arguments[0];
        for (var x = 1; x < arguments.length; x++) {
            for (key in arguments[x])
                if (!(key in target)) target[key] = arguments[x][key];
        }
        return target;
    };

    this.http_build_query = function (array, encode) {
        var parts = [], qs = '';
        for (x in array)
            parts.push(x + '=' + array[x]);
        qs = parts.join('&');
        if (encode === true)
            qs = this.__options.queryParam + '=' + btoa(qs);
        return qs;
    };

    /**
     * Parses a query string and returns an object.
     * @param {string} query - The query string to be parsed.
     * @returns {Object} The parsed query string as an object.
     */
    this.parseStr = function (query) {
        var vars = query.split('&'), params = {};
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split('=');
            params[pair[0]] = pair[1];
        }
        return params;
    };

    /**
     * Generates a URL based on the controller, action, and parameters.
     * @param {string} controller - The controller name.
     * @param {string} action - The action name.
     * @param {Object} params - The parameters for the URL.
     * @param {boolean} encode - Specifies whether to encode the URL or not.
     * @returns {string} The generated URL.
     */
    this.url = function (controller, action, params, encode) {
        var url = this.__options.url;
        if (controller && (i = controller.indexOf('?')) !== -1) {
            params = this.parseStr(controller.substr(i + 1));
            controller = controller.substr(0, i);
        } else if (typeof params != 'object' || params == null)
            params = {};
        if (this.__options.rewrite) {
            if (url.charAt(url.length - 1) != '/')
                url += '/';
            if (typeof controller !== 'undefined') {
                url += controller;
                if (typeof action !== 'undefined')
                    url += '/' + action;
            }
        } else if (typeof controller !== 'undefined') {
            params[this.__options.pathParam] = controller + ((typeof action !== 'undefined') ? '/' + action : '');
        }
        if (Object.keys(params).length == 0)
            return url;
        return url + '?' + this.http_build_query(params, encode);
    };

    /**
     * Sets a value in the data object.
     * @param {string} key - The key of the value to be set.
     * @param {*} value - The value to be set.
     */
    this.set = function (key, value) {
        this.__options.data[key] = value;
    };

    /**
     * Gets a value from the data object.
     * @param {string} key - The key of the value to be retrieved.
     * @param {*} def - The default value to be returned if the key does not exist.
     * @returns {*} The retrieved value.
     */
    this.get = function (key, def) {
        return (typeof this.__options.data[key] == 'undefined') ? def : this.__options.data[key];
    };

    /**
     * Parses the query string of the current URL and returns an object.
     * @param {string} [retVal] - The specific query parameter to retrieve.
     * @returns {Object|string} The parsed query string as an object or a specific query parameter value.
     */
    this.queryString = function (retVal) {
        var queryString = {};
        var parts = document.location.search.substr(1).split('&');
        for (x in parts) {
            item = parts[x].split('=');
            if (typeof retVal == 'undefined') {
                queryString[item[0]] = item[1];
            } else {
                if (retVal == item[0])
                    return item[1];
            }
        }
        return queryString;
    };

    /**
     * Loads a JavaScript file dynamically.
     * @param {string} scriptFile - The URL of the JavaScript file to be loaded.
     */
    this.load = function (scriptFile) {
        var body = document.getElementsByTagName('body')[0];
        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = false;
        script.src = scriptFile;
        body.appendChild(script);
    };

    this.__options = this.extend(options, { "url": "", "data": {}, "rewrite": true });
    if (typeof this.__options.data !== 'object' || this.__options.data === null) this.__options.data = {};
};

/**
 * Delays the execution of a callback function by a specified number of milliseconds.
 *
 * @param {Function} callback - The callback function to be executed after the delay.
 * @param {number} ms - The number of milliseconds to delay the execution.
 */
var delay = (function () {
    var timer = 0;
    return function (callback, ms) {
        clearTimeout(timer);
        timer = setTimeout(callback, ms);
    };
})();

/**
 * Converts a file size in bytes to a human-readable format.
 *
 * @param {number} bytes - The file size in bytes.
 * @param {boolean} si - Specifies whether to use the SI (decimal) or binary units.
 * @returns {string} The human-readable file size.
 */
function humanFileSize(bytes, si) {
    var thresh = si ? 1000 : 1024;
    if (bytes < thresh) return bytes + ' B';
    var units = si ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'] : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    var u = -1;
    do {
        bytes /= thresh;
        ++u;
    } while (bytes >= thresh);
    return bytes.toFixed(1) + ' ' + units[u];
};

/**
 * Extends the Number prototype to convert a number to a human-readable file size.
 *
 * @param {boolean} si - Specifies whether to use the SI (decimal) or binary units.
 * @returns {string} The human-readable file size.
 */
Number.prototype.toBytes = function (si) {
    return humanFileSize(this, si);
};

/**
 * Replaces tagged text in a string with corresponding values from a data object.
 *
 * @param {Object} data - The data object containing the values to replace.
 * @returns {string} The string with the tagged text replaced.
 */
String.prototype.replaceTaggedText = function (data) {
    return this.replaceAll(/\{\{([\W]*)([\w\.]+)\}\}/g, function (item, m1, m2) {
        return m2.split('.').reduce((o, i) => o[i] ? o[i] : '', data);
    });
}

/**
 * Parses a template and replaces placeholders with corresponding values from a data object.
 *
 * @param {Object} data - The data object containing the values to replace.
 * @param {Object} callbacks - The callback functions to be applied to specific elements.
 * @returns {HTMLElement} The parsed template as an HTML element.
 */
HTMLElement.prototype.parseTemplate = function (data, callbacks) {
    let container = document.createElement('div'), clone = this.content.cloneNode(true);
    for (let element of clone.children) {
        element.matchReplace(data, callbacks);
        container.appendChild(element);
    }
    return container;
};

/**
 * Checks if an element matches a condition based on a value.
 *
 * @param {*} value - The value to be checked against the condition.
 * @returns {boolean} True if the element matches the condition, false otherwise.
 */
HTMLElement.prototype.matchCondition = function (value) {
    let val = null;
    return !(((val = this.attributes.getNamedItem('data-value')) && value != val.value)
        || ((val = this.attributes.getNamedItem('data-match')) && !value.match(new RegExp(val.value)))
        || !value);
}

/**
 * Replaces placeholders and applies conditions to an element and its children.
 *
 * @param {Object} data - The data object containing the values to replace.
 * @param {Object} callbacks - The callback functions to be applied to specific elements.
 */
HTMLElement.prototype.matchReplace = function (data, callbacks) {
    let iffing = false, iffed = false;
    for (let item of this.children) {
        let attr = null, string = null;
        if ((attr = item.attributes.getNamedItem('data-if'))) {
            iffing = true;
            if (!item.matchCondition(data[attr.value])) {
                $(item).hide();
                continue;
            }
            iffed = true;
        } else if (iffing === true) {
            if ((attr = item.attributes.getNamedItem('data-elseif'))) {
                if (iffed === true || !item.matchCondition(data[attr.value])) {
                    $(item).hide();
                    continue;
                }
                iffed = true;
            } else if ((attr = item.attributes.getNamedItem('data-else')) && iffed === true) {
                $(item).hide();
                continue;
            } else {
                iffing = iffed = false;
            }
        }
        for (let attr of item.attributes) attr.value = attr.value.replaceTaggedText(data);
        if (item.childElementCount > 0) {
            item.matchReplace(data, callbacks);
            continue;
        }
        string = item.textContent.replaceTaggedText(data);
        if (callbacks) {
            let cbs = item.getAttributeNames().filter(value => Object.keys(callbacks).includes(value));
            if (cbs.length > 0) {
                for (let x in cbs) string = callbacks[cbs[x]](string, item.attributes.getNamedItem(cbs[x]));
            }
        }
        item.textContent = string;
    };
};