$(document).ready(function () {
    $.get(hazaar.url('app', 'graphs')).done(function (graphs) {
        for (x in graphs) {
            var row = $('<div class="row">').appendTo($('#metrics'));
            for (i in graphs[x]) {
                let graph = graphs[x][i];
                let id = x + i, canvas = $('<canvas class="metrics">'), col = $('<div class="col col-' + (12 / graphs[i].length) + '">').appendTo(row);
                $('<div class="panel">').html([
                    $('<div class="panel-header">'),
                    $('<div class="panel-subheader">'),
                    $('<div class="panel-content">').html(canvas)
                ]).appendTo(col).attr('id', 'metrics-' + id);
                $.post(hazaar.url('app', 'stats'), {
                    name: graph.ds,
                    archive: graph.archive,
                    args: { id: id, options: graph }
                }).done(function (data) {
                    if (!data) return;
                    let panel = $('#metrics-' + data.args.id), canvas = panel.children('.panel-content').children('canvas');
                    console.log(panel);
                    var ctx = canvas.get(0).getContext('2d');
                    var myChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: Object.keys(data.ticks),
                            datasets: [{
                                label: data.ds.name,
                                data: Object.values(data.ticks),
                                backgroundColor: data.args.options.bgcolor,
                                borderColor: data.args.options.color,
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true
                                    },
                                    scaleLabel: {
                                        display: true,
                                        labelString: data.args.options.scale
                                    }
                                }]
                            }
                        }
                    });
                    panel.children('.panel-header').html(data.ds.desc);
                    panel.children('.panel-subheader').html(data.archive.desc);
                }).fail(handleError);
            }
        }
    });
});