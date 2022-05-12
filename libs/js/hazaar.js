﻿function HazaarJSHelper(options) {
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
    this.parseStr = function (query) {
        var vars = query.split('&'), params = {};
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split('=');
            params[pair[0]] = pair[1];
        }
        return params;
    };
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
    this.set = function (key, value) {
        this.__options.data[key] = value;
    };
    this.get = function (key, def) {
        return (typeof this.__options.data[key] == 'undefined') ? def : this.__options.data[key];
    };
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

var delay = (function () {
    var timer = 0;
    return function (callback, ms) {
        clearTimeout(timer);
        timer = setTimeout(callback, ms);
    };
})();

/*
 * Date Format 1.2.3
 * (c) 2007-2009 Steven Levithan <stevenlevithan.com>
 * MIT license
 *
 * Includes enhancements by Scott Trenda <scott.trenda.net>
 * and Kris Kowal <cixar.com/~kris.kowal/>
 *
 * Accepts a date, a mask, or a date and a mask.
 * Returns a formatted version of the given date.
 * The date defaults to the current date/time.
 * The mask defaults to dateFormat.masks.default.
 */

var dateFormat = function () {
    var token = /d{1,4}|m{1,4}|yy(?:yy)?|([HhMsTt])\1?|[LloSZ]|"[^"]*"|'[^']*'/g,
        timezone = /\b(?:[PMCEA][SDP]T|(?:Pacific|Mountain|Central|Eastern|Atlantic) (?:Standard|Daylight|Prevailing) Time|(?:GMT|UTC)(?:[-+]\d{4})?)\b/g,
        timezoneClip = /[^-+\dA-Z]/g,
        pad = function (val, len) {
            val = String(val);
            len = len || 2;
            while (val.length < len) val = "0" + val;
            return val;
        };

    // Regexes and supporting functions are cached through closure
    return function (date, mask, utc) {
        var dF = dateFormat;

        // You can't provide utc if you skip other args (use the "UTC:" mask prefix)
        if (arguments.length == 1 && Object.prototype.toString.call(date) == "[object String]" && !/\d/.test(date)) {
            mask = date;
            date = undefined;
        }

        // Passing date through Date applies Date.parse, if necessary
        date = date ? new Date(date) : new Date;
        if (isNaN(date)) throw SyntaxError("invalid date");

        mask = String(dF.masks[mask] || mask || dF.masks["default"]);

        // Allow setting the utc argument via the mask
        if (mask.slice(0, 4) == "UTC:") {
            mask = mask.slice(4);
            utc = true;
        }

        var _ = utc ? "getUTC" : "get",
            d = date[_ + "Date"](),
            D = date[_ + "Day"](),
            m = date[_ + "Month"](),
            y = date[_ + "FullYear"](),
            H = date[_ + "Hours"](),
            M = date[_ + "Minutes"](),
            s = date[_ + "Seconds"](),
            L = date[_ + "Milliseconds"](),
            o = utc ? 0 : date.getTimezoneOffset(),
            flags = {
                d: d,
                dd: pad(d),
                ddd: dF.i18n.dayNames[D],
                dddd: dF.i18n.dayNames[D + 7],
                m: m + 1,
                mm: pad(m + 1),
                mmm: dF.i18n.monthNames[m],
                mmmm: dF.i18n.monthNames[m + 12],
                yy: String(y).slice(2),
                yyyy: y,
                h: H % 12 || 12,
                hh: pad(H % 12 || 12),
                H: H,
                HH: pad(H),
                M: M,
                MM: pad(M),
                s: s,
                ss: pad(s),
                l: pad(L, 3),
                L: pad(L > 99 ? Math.round(L / 10) : L),
                t: H < 12 ? "a" : "p",
                tt: H < 12 ? "am" : "pm",
                T: H < 12 ? "A" : "P",
                TT: H < 12 ? "AM" : "PM",
                Z: utc ? "UTC" : (String(date).match(timezone) || [""]).pop().replace(timezoneClip, ""),
                o: (o > 0 ? "-" : "+") + pad(Math.floor(Math.abs(o) / 60) * 100 + Math.abs(o) % 60, 4),
                S: ["th", "st", "nd", "rd"][d % 10 > 3 ? 0 : (d % 100 - d % 10 != 10) * d % 10]
            };

        return mask.replace(token, function ($0) {
            return $0 in flags ? flags[$0] : $0.slice(1, $0.length - 1);
        });
    };
}();

// Some common format strings
dateFormat.masks = {
    "default": "ddd mmm dd yyyy HH:MM:ss",
    shortDate: "m/d/yy",
    mediumDate: "mmm d, yyyy",
    longDate: "mmmm d, yyyy",
    fullDate: "dddd, mmmm d, yyyy",
    shortTime: "h:MM TT",
    mediumTime: "h:MM:ss TT",
    longTime: "h:MM:ss TT Z",
    isoDate: "yyyy-mm-dd",
    isoTime: "HH:MM:ss",
    isoDateTime: "yyyy-mm-dd'T'HH:MM:ss",
    isoUtcDateTime: "UTC:yyyy-mm-dd'T'HH:MM:ss'Z'"
};

// Internationalization strings
dateFormat.i18n = {
    dayNames: [
        "Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat",
        "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"
    ],
    monthNames: [
        "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
        "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"
    ]
};

// For convenience...
Date.prototype.format = function (mask, utc) {
    return dateFormat(this, mask, utc);
};

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

Number.prototype.toBytes = function (si) {
    return humanFileSize(this, si);
};

String.prototype.replaceTaggedText = function (data) {
    return this.replaceAll(/\{\{([\W]*)([\w\.]+)\}\}/g, function (item, m1, m2) {
        return m2.split('.').reduce((o, i) => o[i] ? o[i] : '', data);
    });
}

HTMLElement.prototype.parseTemplate = function (data, callbacks) {
    let container = document.createElement('div'), clone = this.content.cloneNode(true);
    for (let element of clone.children) {
        element.matchReplace(data, callbacks);
        container.appendChild(element);
    }
    return container;
};

HTMLElement.prototype.matchCondition = function (value) {
    let val = null;
    return !(((val = this.attributes.getNamedItem('data-value')) && value != val.value)
        || ((val = this.attributes.getNamedItem('data-match')) && !value.match(new RegExp(val.value)))
        || !value);
}

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