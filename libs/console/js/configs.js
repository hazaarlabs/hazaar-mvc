$(document).ready(function () {
    $.get(hazaar.url('app', 'configs')).done(function (data) {
        var binder = new dataBinder({ files: data });
        var tbody = $('#configFiles').children('tbody');
        for (x in binder.files) {
            var file = binder.files[x];
            var action = $('<button>').data('i', x);
            if (file.encrypted) {
                action.html('Decrypt').addClass('danger');
            } else {
                action.html('Encrypt').addClass('success');
            }
            tbody.append($('<tr>').html([
                $('<td data-bind="files[' + x + '].name">').html(file.name),
                $('<td data-bind="files[' + x + '].size">').html(file.size),
                $('<td data-bind="files[' + x + '].encrypted">').html((file.encrypted ? 'Yes' : 'No')),
                $('<td>').html(action)
            ]).data('file', file));
        }
        $('#configFiles').find('button').click(function (event) {
            var i = $(this).data('i');
            binder.files[i].encrypted = true;
        });
    });
});