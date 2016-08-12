function HazaarJSHelper(base_url) {
    this.base_url = base_url;
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
}