$.fn.popup = function () {
    var args = arguments;
    this.each(function (index, host) {
        if (!host.show) {
            host.props = $.extend({
                title: "Popup Window",
                buttons: [],
                icon: null,
                modal: false,
                hideOnClose: false
            }, args[0]);
            host.center = function () {
                var x = (window.innerWidth / 2) - (this.__window.width() / 2), y = (window.innerHeight / 2) - (this.__window.height() / 2);
                this.__window.css({ top: y, left: x });
            }
            host.close = function () {
                $(this).trigger('beforeClose');
                this.__overlay.fadeOut(function () {
                    $(host).trigger('closed');
                    if (host.props.hideOnClose === false)
                        host.__overlay.remove();
                });
            };
            host.show = function () {
                if (!$(host).is(':visible')) $(host).show();
                if (this.__overlay) {
                    this.__window.show();
                    this.__overlay.fadeIn();
                } else {
                    this.__window.fadeIn();
                }
                this.center();
            };
            host.btnAction = function (event) {
                var btn = $(this).data('btn');
                if (typeof btn.action == 'function')
                    btn.action.apply(host, event);
                else if (btn.action == 'close')
                    host.close();
                else if (btn.action == 'post' && btn.target) {
                    var data = {}, items = host.__window.find('input,select,textarea').serializeArray();
                    if (btn['data-source']) {
                        $(btn['data-source']).find('input[name],select[name],textarea[name]').each(function (index, item) {
                            var value = item.value;
                            if (item.type == 'checkbox')
                                value = $(item).is(':checked');
                            else if ($(item).attr('aria-hidden') == 'true' && $(item).prev().hasClass('mce-tinymce'))
                                value = tinymce.get('content').getContent();
                            data[item.name] = value;
                        });
                    }
                    for (x in items)
                        data[items[x]['name']] = items[x]['value'];
                    var post = $.post(btn.target, $.extend(btn.args, data));
                    if (host.props.callback)
                        post.done(host.props.callback);
                    if (btn.error)
                        post.error(eval(btn.error));
                    if (btn.done)
                        post.done(eval(btn.done));
                    host.close();
                }
            };
            host.__setIcon = function (name, color) {
                var icon = 'font-awesome', color = '#000';
                if (!this.__icon)
                    this.__icon = $('<div class="popup-window-icon">').appendTo(this.__container);
                switch (name) {
                    case 'working':
                        icon = 'circle-o-notch fa-spin';
                        color = '#333';
                        break;
                    case 'error':
                    case 'danger':
                        icon = 'times-circle';
                        color = '#cf3838';
                        break;
                    case 'info':
                    case 'notice':
                        icon = 'info-circle';
                        color = '#3A85CF';
                        break;
                    case 'warn':
                    case 'warning':
                        icon = 'exclamation-circle';
                        color = '#FF9900';
                        break;
                    case 'question':
                        icon = 'question-circle';
                        color = '#3A85CF';
                        break;
                    case 'user':
                        icon = 'user-circle';
                        color = '#00B36B';
                        break;
                    case 'success':
                        icon = 'check-circle';
                        color = '#00B36B';
                        break;
                }
                this.__icon.html($('<i class="fa fa-' + icon + '">'));
                if (color)
                    this.__icon.css({ color: color });
            }
            host.render = function () {
                this.__overlay = $('<div class="popup-overlay">').appendTo(document.body).toggleClass('modal', this.props.modal);
                this.__window = $('<div class="popup-window">').appendTo(this.__overlay);
                this.__title = $('<div class="popup-window-title">').html(this.props.title).appendTo(this.__window);
                this.__container = $('<div class="popup-container">').appendTo(this.__window);
                this.__close = $('<div class="popup-window-close">').html('X').appendTo(this.__title);
                if (this.props.icon)
                    this.__setIcon(this.props.icon, this.props.iconColor);
                this.props.hideOnClose = ($(this).parent().length > 0);
                this.__container.append(this);
                if (this.props.buttons.length > 0) {
                    this.__buttons = $('<div class="popup-buttons">').appendTo(this.__window);
                    for (x in this.props.buttons) {
                        if (typeof this.props.buttons[x] == 'string')
                            this.props.buttons[x] = { label: this.props.buttons[x], action: 'close' };
                        var btn = $.extend({ label: 'Button', action: 'close', 'class': 'popup-button' }, this.props.buttons[x]);
                        var button = $('<button>').attr('class', btn.class).html(btn.label).data('btn', btn).click(this.btnAction);
                        this.__buttons.append(button);
                    }
                }
                $(this).addClass('popup-content');
                if (this.props.width)
                    this.__window.addClass('static').css({ width: this.props.width });
                if (this.props.height)
                    this.__window.addClass('static').css({ height: this.props.height });
                if (this.props['max-width'])
                    this.__window.css({ 'max-width': this.props['max-width'] });
                if (this.props['max-height'])
                    this.__window.css({ 'max-height': this.props['max-height'] });
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
            if (host.props.close) {
                setTimeout(function () {
                    host.close();
                }, host.props.close);
            }
        } else if (typeof args[0] == 'string') {
            switch (args[0]) {
                case 'close':
                    this.close();
                    break;
                case 'center':
                    this.center();
                    break;
                case 'icon':
                    this.__setIcon(args[1], args[2]);
                    break;
            }
        }
        host.show();
    });
    return this;
};
