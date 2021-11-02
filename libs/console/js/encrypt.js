$(document).ready(function () {
    $.get(hazaar.url('app', 'encrypt')).done(function (data) {
        var binder = new dataBinder({ files: data });
        var tbody = $('#encryptFiles').children('tbody');
        for (x in binder.files.save()) {
            var file = binder.files[x];
            var action = $('<button class="btn">').data('i', x);
            if (file.encrypted.value) {
                action.html('Decrypt').addClass('btn-danger');
            } else {
                action.html('Encrypt').addClass('btn-success');
            }
            tbody.append($('<tr>').html([
                $('<td data-bind="files[' + x + '].name">').html(file.name.value),
                $('<td data-bind="files[' + x + '].size">').html(file.size.value),
                $('<td>').html(action)
            ]).data('file', file));
        }
        $('#encryptFiles').find('button').click(function (event) {
            var i = $(this).data('i');
            var btn = $(this);
            $.post(hazaar.url('app', 'encrypt'), { encrypt: binder.files[i].name.value }).done(function (data) {
                btn.toggleClass('btn-danger', data.encrypt)
                    .toggleClass('btn-success', !data.encrypt)
                    .html((data.encrypt ? 'Decrypt' : 'Encrypt'));
            });
        });
    });
});