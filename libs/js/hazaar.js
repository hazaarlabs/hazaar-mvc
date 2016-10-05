function HazaarJSHelper(base_url, data) {
    this.__base_url = base_url;
    this.__data = (typeof data == 'undefined') ? {} : data;
    this.url = function (controller, action, params) {
        var url = this.__base_url;
        if (typeof controller == 'undefined')
            return url;
        url += controller;
        if (typeof action == 'undefined')
            return url;
        url += '/' + action;
        return url;
    }
    this.set = function (key, value) {
        this.__data[key] = value;
    }
    this.get = function (key, def) {
        return (typeof this.__data[key] == 'undefined') ? def : this.__data[key];
    }
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
    }
}