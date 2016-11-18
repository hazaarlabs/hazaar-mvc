$.fn.taskbar = function () {
    this.addClass('taskbar');
    return this;
};

$.fn.window = function (app, args) {
    args = $.extend(args, { title: "New Window", width: 640, height: 480 });
    obj = this[0];
    obj.app = app;
    obj.show = function () {
        $(this).fadeIn();
    }
    obj.__titlebar = $('<div class="titlebar">')
        .html(args.title)
    .on('mousedown', function (e) {
        obj.dragging = [e.clientX - obj.offsetLeft, e.clientY - obj.offsetTop];
    }).on('mousemove', function (e) {
        if (obj.dragging) {
            obj.style.top = e.clientY - obj.dragging[1] + 'px';
            obj.style.left = e.clientX - obj.dragging[0] + 'px';
        }
    }).on('mouseup', function (e) {
        delete obj.dragging;
    });
    obj.__content = $('<div class="content">')
    .on('click', function () {
        if (obj.dragging) delete obj.dragging;
    });
    obj.app.append(this.hide().append(obj.__titlebar, obj.__content).css({ width: args.width, height: args.height }));
    return obj;
}

$.fn.desktop = function (struct) {
    this.windows = [];
    this.struct = struct;
    this.addClass('desktop');
    this.taskbar = $('<div>').appendTo(this).taskbar();
    this.createWindow = function (text) {
        var win = $('<div class="window">').window(this, { title: text });
        this.windows.push(win);
        return win;
    }
    if (typeof this.struct.init == 'function') this.struct.init.call(this);
};

$(document).ready(function () {
    var app = $('#desktop').desktop({
        init: function () {
            this.createWindow("Test Window").show();
        }
    });
});