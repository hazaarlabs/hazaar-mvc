$(document).ready(function () {
    var graphs = [
        { ds: 'hits', archive: 'max_1hour', scale: 'Count', bgcolor: 'rgba(132, 99, 255, 0.2)', color: 'rgba(132,99,255,1)' },
        { ds: 'exec', archive: 'max_1hour', scale: 'ms', bgcolor: 'rgba(255, 99, 132, 0.2)', color: 'rgba(255,99,132,1)' },
        { ds: 'hits', archive: 'avg_1day', scale: 'Count', bgcolor: 'rgba(132, 99, 255, 0.2)', color: 'rgba(132,99,255,1)' },
        { ds: 'exec', archive: 'avg_1day', scale: 'ms', bgcolor: 'rgba(255, 99, 132, 0.2)', color: 'rgba(255,99,132,1)' }
    ];
    for (x in graphs) {
        $.post(hazaar.url('app', 'stats'), {
            name: graphs[x].ds,
            archive: graphs[x].archive,
            args: { 'id': x, options: graphs[x] }
        }).done(function (graph) {
            var panel = $('#metrics' + graph.args.id), canvas = panel.children('.panel-content').children('canvas');
            var ctx = canvas.get(0).getContext('2d');
            var myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Object.keys(graph.ticks),
                    datasets: [{
                        label: graph.ds.name,
                        data: Object.values(graph.ticks),
                        backgroundColor: graph.args.options.bgcolor,
                        borderColor: graph.args.options.color,
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
                                labelString: graph.args.options.scale
                            }
                        }]
                    }
                }
            });
            panel.children('.panel-header').html(graph.ds.desc);
            panel.children('.panel-subheader').html(graph.archive.desc);
        }).fail(handleError);
    }
});