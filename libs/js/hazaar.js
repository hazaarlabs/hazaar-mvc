function HazaarJSHelper(base_url, data) {
    this.base_url = base_url;
    this.data = (typeof data == 'undefined') ? {} : data;
    this.url = function (controller, action, params) {
        var url = this.base_url;
        if (typeof controller == 'undefined')
            return url;
        url += controller;
        if (typeof action == 'undefined')
            return url;
        url += '/' + action;
        return url;
    }
    this.set = function (key, value) {
        this.data[key] = value;
    }
    this.get = function (key) {
        return this.data[key];
    }
}