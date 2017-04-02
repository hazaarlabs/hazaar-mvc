$.fn.popup = function (arg) {
    this.each(function (index, host) {
        if (!host.show) {
            host.props = $.extend({
                title: "Popup Window",
                buttons: [],
                icon: null,
                modal: false
            }, arg);
            host.center = function () {
                var x = (window.innerWidth / 2) - (this.__window.width() / 2), y = (window.innerHeight / 2) - (this.__window.height() / 2);
                this.__window.css({ top: y, left: x });
            }
            host.close = function () {
                $(this).trigger('beforeClose');
                this.__overlay.fadeOut(function () {
                    $(host).trigger('closed');
                    host.__overlay.remove();
                });
            };
            host.show = function () {
                this.center();
                this.__window.fadeIn();
            };
            host.btnAction = function (event) {
                var action = $(this).data('action');
                if (typeof action == 'function')
                    action.apply(host, event);
                else if (action == 'close')
                    host.close();
            };
            host.render = function () {
                this.__overlay = $('<div class="popup-overlay">').appendTo(document.body).toggleClass('modal', this.props.modal);
                this.__window = $('<div class="popup-window">').appendTo(this.__overlay);
                this.__title = $('<div class="popup-window-title">').html(this.props.title).appendTo(this.__window);
                this.__container = $('<div class="popup-container">').appendTo(this.__window);
                this.__close = $('<div class="popup-window-close">').html('X').appendTo(this.__title);
                if (this.props.icon) {
                    var icon = $('<div class="popup-window-icon">');
                    switch (this.props.icon) {
                        case 'error':
                        case 'danger':
                            this.props.icon = 'times-circle';
                            this.props.iconColor = '#cf3838';
                            break;
                        case 'info':
                        case 'notice':
                            this.props.icon = 'info-circle';
                            this.props.iconColor = '#3A85CF';
                            break;
                        case 'warn':
                        case 'warning':
                            this.props.icon = 'exclamation-circle';
                            this.props.iconColor = '#FF9900';
                            break;
                        case 'question':
                            this.props.icon = 'question-circle';
                            this.props.iconColor = '#3A85CF';
                            break;
                        case 'user':
                            this.props.icon = 'user-circle';
                            this.props.iconColor = '#00B36B';
                            break;
                        case 'success':
                            this.props.icon = 'check-circle';
                            this.props.iconColor = '#00B36B';
                            break;
                    }
                    icon.html($('<i class="fa fa-' + this.props.icon + '">'));
                    if (this.props.iconColor)
                        icon.css({ color: this.props.iconColor });
                    this.__container.append(icon);
                }
                this.__container.append(this);
                if (this.props.buttons.length > 0) {
                    this.__buttons = $('<div class="popup-buttons">').appendTo(this.__window);
                    for (x in this.props.buttons) {
                        if (typeof this.props.buttons[x] == 'string')
                            this.props.buttons[x] = { label: this.props.buttons[x], action: 'close' };
                        var btn = $.extend({ label: 'Button', action: 'close' }, this.props.buttons[x]);
                        this.__buttons.append($('<button>').html(btn.label).data('action', btn.action).click(this.btnAction));
                    }
                }
                $(this).addClass('popup-content');
                if (this.props.width)
                    this.__window.addClass('static').css({ width: this.props.width });
                if (this.props.height)
                    this.__window.addClass('static').css({ height: this.props.height });
            };
            host.__move = function () {
                host.__window.css({ left: event.pageX - host.__offset[0], top: event.pageY - host.__offset[1] });
            };
            host.registerEvents = function () {
                this.__close.click(function () {
                    host.close();
                    return false;
                });
                this.__title.on('mousedown', function (event) {
                    if (host.__close.is(event.target))
                        return false;
                    host.__offset = [event.offsetX, event.offsetY];
                    $(window).on('mousemove', host.__move);
                }).on('mouseup', function (event) {
                    $(window).off('mousemove', host.__move);
                });

            };
            host.render();
            host.registerEvents();
            host.show();
            if (host.props.close) {
                setTimeout(function () {
                    host.close();
                }, host.props.close);
            }
        } else if (typeof arg == 'string') {
            switch (arg) {
                case 'close':
                    this.close();
                    break;
                case 'center':
                    this.center();
                    break;
            }
        }
    });
    return this;
};
