/*
 jQWidgets v2.8.3 (2013-Apr-29)
 Copyright (c) 2011-2013 jQWidgets.
 License: http://jqwidgets.com/license/
 Author: Jamie Carl <jazz@funkynerd.com>
 */
(function(a) {
    a.jqx.jqxWidget("jqxEditor", "", {});
    a.extend(a.jqx._jqxEditor.prototype, {
        defineInstance : function() {
            this.width = null;
            this.height = null;
            this.disabled = false;
            this.theme = null;
            this.tools = ['clear', 'save', 'print', 'undo', 'redo', 'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'justifyleft', 'justifycenter', 'justifyright', 'justifyfull', 'inserthorizontalrule', 'indent', 'outdent', 'insertunorderedlist', 'insertorderedlist', 'insertimage', 'createlink', 'html', ['formatblock', 'fontName', 'fontSize', 'forecolor', 'backcolor']];
            this.tooltips = {
                'clear' : 'New document',
                'save' : 'Save document',
                'print' : 'Print document',
                'undo' : 'Undo last change',
                'redo' : 'Redo last undo',
                'bold' : 'Bold',
                'italic' : 'Italic',
                'underline' : 'Underline',
                'strikethrough' : 'Strikethrough',
                'subscript' : 'Subscript',
                'superscript' : 'Superscript',
                'justifyleft' : 'Align text left',
                'justifycenter' : 'Center text',
                'justifyright' : 'Align text right',
                'justifyfull' : 'Justify',
                'inserthorizontalrule' : 'Insert horizontal rule',
                'indent' : 'Indent',
                'outdent' : 'Outdent',
                'insertunorderedlist' : 'Insert unordered list',
                'insertorderedlist' : 'Insert ordered list',
                'insertimage' : 'Insert image',
                'createlink' : 'Toggle hyperlink',
                'html' : 'View source'
            };
            this.fonts = ['Arial', 'Arial Black', 'Avant Garde', 'Bookman', 'Comic Sans MS', 'Courier', 'Courier New', 'Georgia', 'Helvetica', 'Impact', 'Palatino', 'Sans-Serif', 'Serif', 'Times New Roman', 'Times', 'Trebuchet MS', 'Verdana'];
            this.editable = true;
            this.imageBrowser = null;
            this.stylesheets = null;
            this.importstyles = false;
            this.default_color = {
                'forecolor' : '000000',
                'backcolor' : 'ffffff'
            };
            this.messages = {};
            this.layout = a('<table class="jqx-editor-layout"></table>');
            this.toolbar = '<ul class="jqx-editor-tb"></ul>';
            this.tbitem = '<li class="jqx-editor-tb-item"></li>';
            this.tbbutton = '<button class="jqx-editor-tb-button"></button>';
            this.tbicon = '<div class="jqx-editor-tb-icon"></div>';
            this.editbox = a('<td class="jqx-editor-editor"></td>');
            this.iframe = a('<iframe>');//.attr('frameborder', 0);
            this.editorDoc = null;
            this.editor = null;
            this.row = '<tr></tr>';
            this.item = '<td></td>';
            this.displayItems = {
                'strong' : 'bold',
                'b' : 'bold',
                'i' : 'italic',
                'em' : 'italic',
                'u' : 'underline',
                'strike' : 'strikethrough',
                'sup' : 'superscript',
                'sub' : 'subscript',
                'ul' : 'insertunorderedlist',
                'ol' : 'insertorderedlist',
                'a' : 'createlink',
                'center' : 'justifycenter'
            };
            this.toolGroups = [['justifyleft', 'justifyright', 'justifycenter', 'justifyfull'], ['subscript', 'superscript']];
            this.formatItems = {
                'h1' : 'Header 1',
                'h2' : 'Header 2',
                'h3' : 'Header 3',
                'h4' : 'Header 4',
                'h5' : 'Header 5',
                'h6' : 'Header 6',
                'p' : 'Paragraph',
                'pre' : 'Preformatted'
            };
            this.sizes = {
                1 : 'Very Small',
                2 : 'Small',
                3 : 'Normal',
                4 : 'Large',
                5 : 'Very Large',
                6 : 'Huge',
                7 : 'Very Huge'
            };
            this.buttons = {};
            this.ta_mode = false;
            this.selection = false;
            this.range = null;
            this.changed = false;
            this.events = ['change', 'execute', 'keydown', 'keyup', 'save', 'select', 'modechange'];
        },
        createInstance : function(b) {
            var o = this;
            this._render();
            setTimeout(function(){
                o.editorDoc = o.iframe.get(0).contentWindow.document;
                if (this.importstyles) {
                    $(document.head).find('link[rel="stylesheet"]').each(function(index, item) {
                        a(o.editorDoc.head).append($('<link rel="stylesheet" type="text/css">').attr('href', item.href));
                    });
                }
                if (a.isArray(this.stylesheets)) {
                    a.each(this.stylesheets, function(index, item) {
                        a(o.editorDoc.head).append($('<link rel="stylesheet" type="text/css">').attr('href', item));
                    });
                }
                o.editorDoc.designMode = "on";
                try{
                    o.editorDoc.execCommand("undo", false, null);
                } catch(e){
                    alert("The jqxEditor is not supported by your browser.");
                }
                o.editor = a(o.editorDoc.body);
                a.extend(this.tooltips, this.messages);
                o._addHandlers();
                o._addContent(content);
            }, 10);
        },
        _addHandlers : function() {
            var o = this;
            this.addHandler(this.editor, "blur", function() {
                if (o.host.is('textarea')) {
                    o.host.html(a(this).html());
                }
                if (o.changed) {
                    o._raiseEvent(0);
                    o.changed = false;
                }
            });
            this.addHandler(this.editor, "beforedeactivate", function(event) {
                if (o.editorDoc.selection && doc.selection.createRange) {
                    o.range = o.editorDoc.selection.createRange();
                }
            });
            this.addHandler(this.editor, 'focus', function(event) {
                if (o.range)
                    o.range.select();
            });
            this.addHandler(this.editor, "mousedown", function(event) {
                if (a('.jqx-editor-tb-dropbutton').length > 0)
                    a('.jqx-editor-tb-dropbutton').jqxDropDownButton('close');
                if (a('.jqx-editor-tb-droplist').length > 0)
                    a('.jqx-editor-tb-droplist').jqxDropDownList('close');
                o.selection = true;
            });
            this.addHandler(this.editor, "mouseup", function(event) {
                if (o.selection == true) {
                    var s = o.editable;
                    o.editable = false;
                    o._clearSelection();
                    o._toggleSelection(a(event.target));
                    o.selection = false;
                    o._raiseEvent(5);
                    o.editable = s;
                }
            });
            this.addHandler(this.editor, "keydown", function(event) {
                if (event.ctrlKey == true && event.which > 64) {
                    o._toggleButton(String.fromCharCode(event.which).toLowerCase());
                }
                o._raiseEvent(2);
            });
            this.addHandler(this.editor, "keyup", function(event) {
                if (event.which >= 37 && event.which <= 40) {
                    o._clearSelection();
                    o._toggleSelection(a(o.editorDoc.getSelection().focusNode).parent());
                }
                o.changed = true;
                o._raiseEvent(3);
            });
            this.addHandler(this.editor, "dblclick", function(event) {
                if (event.target.tagName.toLowerCase() == 'img') {
                    var image = event.target;
                    var btnInsert = a('<button>').html('Update').jqxButton({
                        theme : o.theme
                    });
                    var btnCancel = a('<button>').html('Cancel').jqxButton({
                        theme : o.theme
                    });
                    config = {
                        width : '30em',
                        okButton : btnInsert,
                        cancelButton : btnCancel,
                        items : [{
                            'name' : 'editorImageURL',
                            'label' : 'Web address:',
                            'value' : image.src,
                            'type' : 'text'
                        }, {
                            'name' : 'editorImageAlt',
                            'label' : 'Alternate text:',
                            'value' : image.alt,
                            'type' : 'text'
                        }]
                    };
                    o._popupWindow('Insert image', config).on('close', function(event) {
                        if (event.args.dialogResult.OK == true) {
                            image.src = a(this).find('#editorImageURL').val();
                            image.alt = a(this).find('#editorImageAlt').val();
                        }
                    });
                }
            });
        },
        _removeHandlers : function() {
            if (this.editor) {
                this.removeHandler(this.editor, 'blur');
                this.removeHandler(this.editor, 'beforedeactivate');
                this.removeHandler(this.editor, 'focus');
                this.removeHandler(this.editor, 'mousedown');
                this.removeHandler(this.editor, 'mouseup');
                this.removeHandler(this.editor, 'keydown');
                this.removeHandler(this.editor, 'keyup');
                this.removeHandler(this.editor, 'dblclick');
            }
        },
        _render : function() {
            var o = this;
            var host = this.host;
            this._removeHandlers();
            if (this.host.get(0).tagName == 'TEXTAREA') {
                content = this.host.text();
                var ta = this.host;
                ta.css({
                    display : 'none'
                });
                host = a('<div>');
                this.host.after(host.append(this.layout));
                this.editbox.append(ta);
                this.ta_mode = true;
            } else {
                content = this.host.html();
                this.host.html(this.layout);
            }
            this._renderToolbar(this.tools);
            this.layout.append(a(this.row).append(this.editbox.append(this.iframe)));
            this.layout.addClass(this.toThemeProperty("jqx-rc-all"));
            host.attr('role', 'textbox');
            host.css('width', this.width);
            host.css('height', this.height);
            host.addClass(this.toThemeProperty("jqx-editor"));
            host.addClass(this.toThemeProperty("jqx-widget"));
            host.addClass(this.toThemeProperty("jqx-rc-all"));
            host.addClass(this.toThemeProperty("jqx-fill-state-normal"));
        },
        _renderToolbar : function(tb_items) {
            var o = this;
            var tb = a(this.toolbar);
            this.layout.append(a(this.row).append(a(this.item).append(tb).addClass('jqx-editor-tb-wrap')));
            a.each(tb_items, function(command, item) {
                if (a.isArray(item)) {
                    o._renderToolbar(item);
                } else {
                    o._renderToolbarItem((item.toLowerCase ? item.toLowerCase() : item), tb);
                }
            });
        },
        _renderToolbarItem : function(command, row) {
            var tbitem = a(this.tbitem);
            row.append(tbitem);
            switch(command) {
                case 'formatblock':
                    var attr = {
                        width : 150,
                        height : 28,
                        autoDropDownHeight : true,
                        placeHolder : 'Style',
                        source : a.map(this.formatItems, function(l, c) {
                            return {
                                value : c,
                                label : l
                            };
                        }),
                        theme : this.theme,
                        renderer : function(index, label, value) {
                            return '<' + value + ' style="padding: 0px; margin: 0px;">' + label + '</' + value + '>';
                        }
                    };
                    item = a('<div></div>').addClass('jqx-editor-tb-droplist');
                    tbitem.append(item)
                    item.jqxDropDownList(attr);
                    this._addCommandHandler(item, 'change', command);
                    break;
                case 'fontname':
                    var attr = {
                        width : 150,
                        height : 28,
                        autoDropDownHeight : true,
                        placeHolder : 'Font family',
                        source : this.fonts,
                        theme : this.theme,
                        renderer : function(index, label, value) {
                            return '<span style="font-family: ' + value + ';">' + label + '<span>';
                        }
                    };
                    item = a('<div></div>').addClass('jqx-editor-tb-droplist');
                    tbitem.append(item);
                    item.jqxDropDownList(attr);
                    this._addCommandHandler(item, 'change', command);
                    break;
                case 'fontsize':
                    var attr = {
                        width : 150,
                        height : 28,
                        autoDropDownHeight : true,
                        placeHolder : 'Font size',
                        source : this.sizes,
                        theme : this.theme
                    };
                    item = a('<div></div>').addClass('jqx-editor-tb-droplist');
                    tbitem.append(item);
                    item.jqxDropDownList(attr);
                    this._addCommandHandler(item, 'select', command);
                    break;
                case 'forecolor':
                case 'backcolor':
                    var picker = a('<div style="padding: 3px 3px 5px 3px;"></div>').attr('id', 'picker-' + command);
                    item = a('<div></div>').addClass('jqx-editor-tb-dropbutton').attr('id', command);
                    tbitem.append(item);
                    item.append(picker);
                    item.jqxDropDownButton({
                        width : 48,
                        height : 28,
                        theme : this.theme
                    });
                    var content = a('<div>');
                    var bar = a('<div class="jqx-editor-color-bar">').attr('id', 'bar-' + command).css('background-color', '#' + this.default_color[command]);
                    content.append(a(this.tbicon).addClass('jqx-editor-tb-icon-' + command), bar);
                    item.jqxDropDownButton('setContent', content);
                    picker.jqxColorPicker({
                        color : this.default_color[command],
                        colorMode : 'hue',
                        width : 220,
                        height : 200,
                        theme : this.theme
                    });
                    this._addCommandHandler(picker, 'colorchange', command);
                    break;
                default:
                    var tooltip = null;
                    var action = null;
                    if ( typeof command == 'object') {
                        action = command.exec;
                        tooltip = command.tooltip;
                        command = 'custom';
                    } else {
                        tooltip = this.tooltips[command];
                    }
                    var icon = a(this.tbicon).addClass('jqx-editor-tb-icon-' + command).attr('title', tooltip);
                    var item = a(this.tbbutton).addClass('jqx-editor-tb-button').append(icon);
                    if (a.inArray(command, ['clear', 'save', 'print', 'custom']) >= 0) {
                        item.jqxButton({
                            theme : this.theme
                        });
                    } else {
                        item.jqxToggleButton({
                            theme : this.theme
                        });
                    }
                    tbitem.append(item);
                    this._addCommandHandler(item, 'click', command, action);
                    break;
            }
            item.attr('data-command', command);
            this.buttons[command] = item;
        },
        _addContent : function(content) {
            this.editor.html(content);//.attr('spellcheck', false).attr('autocorrect', 'off');
        },
        _clearSelection : function() {
            var o = this;
            a.each(this.buttons, function(index, obj) {
                if (obj.hasClass('jqx-editor-tb-droplist')) {
                    obj.jqxDropDownList('setContent', obj.jqxDropDownList('placeHolder'));
                    obj.jqxDropDownList('selectIndex', -1);
                } else if (obj.hasClass('jqx-editor-tb-dropbutton')) {
                    var command = obj.attr('id');
                    var flip = (command == 'forecolor') ? true : false;
                    a('#bar-' + command).css('background-color', '#' + o.default_color[command]);
                    a('#picker-' + command).jqxColorPicker('setColor', '#' + o.default_color[command]);
                } else {
                    obj.jqxToggleButton('unCheck');
                }
            });
        },
        _toggleButton : function(tag) {
            var o = this;
            if (this.displayItems[tag]) {
                var command = this.displayItems[tag].toLowerCase();
                if (a.inArray(command, o.buttons)) {
                    o.buttons[command].jqxToggleButton('check');
                }
            };
        },
        _toggleSelection : function(obj) {
            var sb, fb, cb, bb, ab;
            while (!obj.is('body')) {
                var tag = obj.get(0).tagName.toLowerCase();
                if (this.buttons.formatblock && this.formatItems[tag]) {
                    this.buttons.formatblock.jqxDropDownList('setContent', this.formatItems[tag]);
                } else {
                    this._toggleButton(tag);
                }
                if (this.buttons.fontSize && !sb && ( size = obj.prop('size'))) {
                    this.buttons.fontSize.jqxDropDownList('selectIndex', size - 1);
                    sb = true;
                }
                if (this.buttons.fontName && !fb && ( font = obj.css('font-family'))) {
                    this.buttons.fontName.jqxDropDownList('selectIndex', a.inArray(font, this.fonts));
                    fb = true;
                }
                if (this.buttons.forecolor && !cb && ( fcolor = this._rgbToHex(obj.css('color')))) {
                    a('#bar-forecolor').css('background-color', '#' + fcolor);
                    a('#picker-forecolor').jqxColorPicker('setColor', fcolor);
                    cb = true;
                }
                if (this.buttons.backcolor && !bb && ( bcolor = this._rgbToHex(obj.css('background-color')))) {
                    a('#bar-backcolor').css('background-color', '#' + bcolor);
                    a('#picker-backcolor').jqxColorPicker('setColor', bcolor);
                    bb = true;
                }
                if (!ab && ( align = obj.css('text-align'))) {
                    var map = {
                        'justify' : 'justifyfull',
                        'left' : 'justifyleft',
                        'right' : 'justifyright',
                        'center' : 'justifycenter'
                    };
                    if (this.buttons[map[align]]) {
                        this.buttons[map[align]].jqxToggleButton('toggle');
                    }
                    ab = true;
                }
                obj = obj.parent();
            }
        },
        _rgbToHex : function(color) {
            if (color) {
                if ( rgb = color.match(/rgb?\((.*)\)/)) {
                    var cols = rgb[1].split(',');
                    return ((1 << 24) + (parseInt(cols[0]) << 16) + (parseInt(cols[1]) << 8) + parseInt(cols[2])).toString(16).substr(1);
                } else if (color.substr(0, 1) == '#') {
                    return color.substr(1);
                }
            }
            return null;
        },
        _addCommandHandler : function(item, on, cmd, data) {
            var o = this;
            switch(cmd) {
                case 'save':
                    action = function() {
                        o._raiseEvent(4, {
                            content : o.editor.html()
                        });
                        return false;
                    };
                    break;
                case 'clear':
                    action = function() {
                        o.setValue('');
                        if (o.ta_mode == true) {
                            o.host.html(a(this).html());
                        }
                        o.editor.focus();
                        o._raiseEvent(0);
                        return false;
                    };
                    break;
                case 'formatblock':
                    action = function() {
                        o.execute(a(this).attr('data-command'), '<' + a(this).val() + '>');
                        return false;
                    };
                    break;
                case 'fontsize':
                    action = function() {
                        var val = a(this).jqxDropDownList('getSelectedIndex') + 1;
                        o.execute(a(this).attr('data-command'), val);
                        return false;
                    };
                    break;
                case 'forecolor':
                case 'backcolor':
                    action = function(event) {
                        a('#bar-' + cmd).css('background', '#' + event.args.color.hex);
                        o.execute(cmd, event.args.color.hex);
                        return false;
                    };
                    break;
                case 'html':
                    action = function() {
                        o.editmode(!o.editable);
                        return false;
                    };
                    break;
                case 'insertimage':
                    if (this.imageBrowser) {
                        action = function() {
                            var browser = a('<div>');
                            browser.jqxImageBrowser(a.extend({
                                theme : o.theme,
                                isModal : true
                            }, o.imageBrowser)).on('imageselected', function(event) {
                                o._insertImage(event.args.src, event.args.alt, event.args.size, event.args.align, event.args.margin);
                                browser.remove();
                            }).on('close', function(event) {
                                o.buttons[cmd].jqxToggleButton('unCheck');
                            });
                            return false;
                        };
                    } else {
                        action = function() {
                            var btnInsert = a('<button>').html('Insert').jqxButton({
                                theme : o.theme
                            });
                            var btnCancel = a('<button>').html('Cancel').jqxButton({
                                theme : o.theme
                            });
                            config = {
                                width : '30em',
                                okButton : btnInsert,
                                cancelButton : btnCancel,
                                items : [{
                                    'name' : 'editorImageURL',
                                    'label' : 'Web address:',
                                    'value' : 'http://',
                                    'type' : 'text'
                                }, {
                                    'name' : 'editorImageAlt',
                                    'label' : 'Alternate text:',
                                    'type' : 'text'
                                }]
                            };
                            o._popupWindow('Insert image', config).on('close', function(event) {
                                if (event.args.dialogResult.OK == true) {
                                    var src = a(this).find('#editorImageURL').val();
                                    var alt = a(this).find('#editorImageAlt').val();
                                    o._insertImage(src, alt);
                                }
                            }).on('close', function(event) {
                                o.buttons[cmd].jqxToggleButton('unCheck');
                            });
                            return false;
                        };
                    }
                    break;
                case 'createlink':
                    action = function() {
                        if (a(this).jqxToggleButton('toggled')) {
                            var selection = null;
                            if (o.editorDoc.getSelection) {
                                selection = o.editorDoc.getSelection();
                            } else if (o.editorDoc.selection) {
                                selection = o.editorDoc.selection.createRange().text;
                            }
                            var btnCreate = a('<button>').html('Create').jqxButton({
                                theme : o.theme
                            });
                            var btnCancel = a('<button>').html('Cancel').jqxButton({
                                theme : o.theme
                            });
                            config = {
                                width : '30em',
                                okButton : btnCreate,
                                cancelButton : btnCancel,
                                items : [{
                                    'name' : 'editorLinkURL',
                                    'label' : 'Web address:',
                                    'value' : 'http://',
                                    'type' : 'text'
                                }, {
                                    'name' : 'editorLinkText',
                                    'label' : 'Text:',
                                    'value' : selection.toString(),
                                    'type' : 'text'
                                }, {
                                    'name' : 'editorLinkTooltip',
                                    'label' : 'Tooltip:',
                                    'type' : 'text'
                                }]
                            };
                            o._popupWindow('Create hyperlink', config).on('close', function(event) {
                                if (event.args.dialogResult.OK == true) {
                                    if ( matches = a(this).find('#editorLinkURL').val().match(/^http:\/\/(.+)a/)) {
                                        var link = a('<a>').attr('href', matches[0]);
                                        if (!( text = a(this).find('#editorLinkText').val())) {
                                            text = matches[1];
                                        }
                                        if (a(this).find('#editorLinkTarget').is(':checked'))
                                            link.attr('target', '_blank');
                                        if ( tooltip = a(this).find('#editorLinkTooltip').val())
                                            link.attr('title', tooltip);
                                        link.html(text);
                                        o.execute('insertHTML', link.get(0).outerHTML);
                                    }
                                }
                            });
                        } else {
                            o.execute('unlink');
                        }
                        return false;
                    };
                    break;
                case 'custom':
                    action = function() {
                        data.call(o.host);
                        return false;
                    };
                    break;
                default:
                    action = function() {
                        var command = a(this).attr('data-command');
                        var obj = this;
                        o.execute(command, a(this).val());
                        a.each(o.toolGroups, function(index, group) {
                            if (a.inArray(command, group) > -1) {
                                a.each(group, function(index, item) {
                                    if (item != command)
                                        o.buttons[item].jqxToggleButton('unCheck');
                                });
                            }
                        });
                        return false;
                    };
                    break;
            }
            o.addHandler(item, on, action);
        },
        _popupWindow : function(title, config) {
            var win = a('<div class="jqx-editor-window">');
            var content = a('<ul>');
            a.each(config.items, function(index, item) {
                var input = a('<input type="text" id="' + item.name + '">');
                if (item.value) {
                    input.attr('value', item.value);
                }
                content.append(a('<li>').addClass('jqx-editor-window-' + item.type).append(a('<label for="' + item.name + '">').append(item.label), input));
            });
            delete config.items;
            content.append(a('<li>').addClass('jqx-editor-window-buttons').append(config.okButton, ' ', config.cancelButton));
            win.append(a('<div>').html(title), a('<div>').html(content));
            win.jqxWindow(a.extend({
                isModal : true,
                theme : this.theme/*,
                 dragArea : {
                 top : this.layout.position().top,
                 left : this.layout.position().left,
                 width : this.layout.width(),
                 height : this.layout.height()
                 }*/
            }, config));
            return win;
        },
        _insertImage : function(src, alt, size, align, margin) {
            var img = a('<img>').attr('src', src);
            if (alt)
                img.attr('alt', alt).attr('title', alt);
            if (size)
                img.css('width', size);
            if (align)
                img.css('float', align);
            if (margin)
                img.css('margin', margin);
            this.execute('insertHTML', img.get(0).outerHTML);
        },
        execute : function(cmd, args) {
            if (this.editable) {
                this.editor.focus();
                if (this.range) {
                    if (cmd.toLowerCase() == 'inserthtml' && this.range.pasteHTML) {
                        this.range.pasteHTML(args);
                    } else {
                        this.range.execCommand(cmd, false, args);
                    }
                } else {
                    this.editorDoc.execCommand(cmd, false, args);
                }
                this._raiseEvent(1, {
                    command : cmd,
                    args : args
                });
                this.changed = true;
            }
        },
        destroy : function() {
            this.host.remove();
        },
        val : function(value) {
            if ( typeof value != 'undefined') {
                this.editor.html(value);
            } else {
                return this.editor.html();
            }
        },
        getValue : function() {
            return this.val();
        },
        setValue : function(value) {
            return this.editor.html(value);
        },
        disable : function() {
            return this.editor.attr('contentEditable', false);
        },
        enable : function() {
            return this.editor.attr('contentEditable', true);
        },
        editable : function() {
            return (this.editor.attr('contentEditable') == 'true' ? true : false);
        },
        editmode : function(value) {
            if (!value && this.editable) {
                this._removeHandlers();
                this.editor.attr('contentEditable', false);
                this._raiseEvent(6);
                var content = document.createTextNode(this.editor.html());
                var pre = a('<pre>');
                pre.html(content).attr({
                    'id' : 'sourceText',
                    'contentEditable' : true
                }).css('height', '100%');
                this.editor.html(pre);
                if (this.buttons.hasOwnProperty('html'))
                    this.buttons.html.addClass('active');
                pre.focus();
            } else if (value && !this.editable) {
                var content = this.editor.find('#sourceText').text();
                this.editor.html(content);
                if (this.buttons.hasOwnProperty('html'))
                    this.buttons.html.removeClass('active');
                this.editor.attr('contentEditable', true);
                this._addHandlers();
            }
            this.editable = value;
        },
        clear : function() {
            this.setValue('');
        },
        print : function() {
            this.execute('print');
        },
        save : function() {
            this._raiseEvent(4);
        },
        _raiseEvent : function(f, c) {
            if (c == undefined) {
                c = {
                    owner : null
                };
            }
            var d = this.events[f];
            c.owner = this;
            var e = new jQuery.Event(d);
            e.owner = this;
            e.args = c;
            if (e.preventDefault) {
                e.preventDefault();
            }
            return this.host.trigger(e);
        }
    });
})(jQuery);

(function(a) {
    a.jqx.jqxWidget("jqxImageBrowser", "", {});
    a.extend(a.jqx._jqxImageBrowser.prototype, {
        defineInstance : function() {
            this.theme = null;
            this.width = "50%";
            this.height = "50%";
            this.messages = null;
            this.isModal = false;
            this.source = false;
            this.title = 'Insert Image';
            this.content = a('<div>');
            this.path = a('<div class="jqx-image-browser-path jqx-image-browser-box">');
            this.container = a('<div class="jqx-image-browser-images jqx-image-browser-box">');
            this.list = a('<ul>');
            this.okButton = a('<button>').append('Insert');
            this.cancelButton = a('<button>').append('Cancel');
            this.buttons = a('<div class="jqx-image-browser-buttons">').append(this.okButton, this.cancelButton);
            this.inputAlt = a('<input type="text">');
            this.alignDropList = a('<div>');
            this.sizeDropList = a('<div>');
            this.labelMargin = a('<span class="jqx-image-browser-label jqx-item">').html('Margin: ');
            this.inputMargin = a('<div>').css('float', 'right');
            this.options = a('<ul>').append(a('<li>').append(this.inputAlt), a('<li>').append(this.sizeDropList), a('<li>').append(this.alignDropList), a('<li>').append(this.labelMargin, this.inputMargin));
            this.optionContainer = a('<div class="jqx-image-browser-box">').append(this.options);
            this.align = ['left', 'right', 'center'];
            this.sizes = {
                'Small' : '100',
                'Medium' : '400',
                'Large' : '800',
                'Original' : null
            };
            this.events = ['imageselected'];
        },
        createInstance : function(b) {
            this.path.addClass(this.toThemeProperty('jqx-widget-content'));
            this.container.addClass(this.toThemeProperty('jqx-widget-content'));
            this.optionContainer.addClass(this.toThemeProperty('jqx-widget-content'));
            this.buttons.children().jqxButton({
                theme : this.theme
            });
            this.inputAlt.jqxInput({
                theme : this.theme,
                height : '25px',
                placeHolder : "Alternate text"
            });
            this.sizeDropList.jqxDropDownList({
                width : "75px",
                height : "25px",
                source : a.map(this.sizes, function(v, l) {
                    return {
                        label : l,
                        value : v
                    };
                }),
                theme : this.theme,
                placeHolder : 'Size',
                autoDropDownHeight : true,
                displayMember : 'label',
                valueMember : 'value'
            });
            this.alignDropList.jqxDropDownList({
                width : "100px",
                height : "25px",
                source : this.align,
                theme : this.theme,
                placeHolder : 'Alignment',
                autoDropDownHeight : true
            });
            this.inputMargin.jqxNumberInput({
                theme : this.theme,
                width : '50px',
                height : '25px',
                spinButtons : true,
                decimalDigits : 0,
                inputMode : 'simple'
            });
            this.content.append(this.path, this.container.append(this.list), this.optionContainer, this.buttons);
            this.host.append(a('<div>').html(this.title), this.content);
            this.host.jqxWindow({
                theme : this.theme,
                isModal : this.isModal,
                width : this.width,
                minWidth : '300px',
                okButton : this.okButton,
                cancelButton : this.cancelButton
            });
            this._addHandlers();
            this.databind(this.source);
        },
        _addHandlers : function() {
            var o = this;
            this.addHandler(this.okButton, 'click', function(event) {
                var uid = o.list.children('li.jqx-tree-item-selected').first().attr('data-uid');
                var value = o.source._source.imageUrl.replace(/\{0\}/, o.source.records[uid].name).replace(/\{1\}/, o.sizeDropList.val() || 'null');
                var data = {
                    src : value,
                    alt : o.inputAlt.val() || value,
                    align : o.alignDropList.val() || null,
                    margin : o.inputMargin.val() || null,
                    size : o.sizeDropList.val() || null
                };
                o._raiseEvent(0, data);
            });
        },
        databind : function(adapter) {
            var o = this;
            var funcBind = function(data, s) {
                if (adapter.totalrecords > 0) {
                    a.each(adapter.records, function(index, record) {
                        if (record.type == 'f') {
                            var src = adapter._source.thumbnailUrl + '?path=' + record.name;
                            var img = a('<img>').attr('src', src).addClass('jqx-image-browser-image');
                            var item = a('<li>').append(img);
                            var size = o._readablizeBytes(record.size);
                            item.append(a('<strong>').html(record.name), a('<span>').html(size)).attr('data-uid', record.uid);
                            o.list.append(item);
                        }
                    });
                    o.list.find('li').click(function() {
                        a(this).parent().children('li').removeClass(o.toThemeProperty("jqx-tree-item-selected")).removeClass(o.toThemeProperty("jqx-fill-state-pressed"));
                        a(this).addClass(o.toThemeProperty("jqx-tree-item-selected")).addClass(o.toThemeProperty("jqx-fill-state-pressed"));
                    }).mouseover(function() {
                        a(this).addClass(o.toThemeProperty("jqx-tree-item-hover")).addClass(o.toThemeProperty("jqx-fill-state-hover"));
                    }).mouseout(function() {
                        a(this).removeClass(o.toThemeProperty("jqx-tree-item-hover")).removeClass(o.toThemeProperty("jqx-fill-state-hover"));
                    });
                }
            };
            adapter.unbindDownloadComplete(this.element.id);
            adapter.bindDownloadComplete(this.element.id, funcBind);
            adapter.dataBind();
        },
        _readablizeBytes : function(bytes) {
            var s = ['bytes', 'kB', 'MB', 'GB', 'TB', 'PB'];
            var e = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, Math.floor(e))).toFixed(2) + " " + s[e];
        },
        _raiseEvent : function(f, c) {
            if (c == undefined) {
                c = {
                    owner : null
                };
            }
            var d = this.events[f];
            c.owner = this;
            var e = new jQuery.Event(d);
            e.owner = this;
            e.args = c;
            if (e.preventDefault) {
                e.preventDefault();
            }
            var b = this.host.trigger(e);
            return b;
        },
        remove : function() {
            this.host.remove();
        }
    });
})(jQuery);
