$(document).ready(function () {
    $.post(hazaar.url('app', 'stats'), {
        name: 'hits',
        archive: 'avg_min_1hour'
    }).done(function (data) {
        console.log(data.ticks);
    });
});