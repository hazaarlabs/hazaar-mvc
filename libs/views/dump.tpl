<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <title>Hazaar Dump</title>

    <!-- Google font -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,700,900" rel="stylesheet">

    <style>
        :root {
            --elem-padding: 2rem;
            --elem-margin: 2rem;
            --dump-radius: 1rem;
            --bg-color: #212A37;
            --fg-color: #c6d3e4;
            --fm-color: #a21c1c;
            --mt-color: #999999;
            --shadow-color: #1a212a;
            color-scheme: dark;
        }

        * {
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
        }

        body {
            padding: 0;
            margin: 0;
        }

        #dumppage {
            display: flex;
            flex-direction: column;
            font-family: 'Montserrat', sans-serif;
            background: var(--bg-color);
            height: 100vh;

            .dumpheader {
                background: var(--bg-color);
                display: flex;
                flex-direction: row;
                width: 100%;
                padding: calc(var(--elem-padding) / 2) var(--elem-padding);
                box-shadow: 0 4px 8px var(--shadow-color);
                z-index: 1;

                .actions {
                    align-self: center;

                    a {
                        display: inline-block;
                        text-transform: uppercase;
                        color: var(--fm-color);
                        text-decoration: none;
                        border: 2px solid;
                        background: transparent;
                        padding: 10px 40px;
                        font-size: 14px;
                        font-weight: 700;
                        -webkit-transition: 0.5s color;
                        transition: 0.5s color;
                        cursor: pointer;
                    }

                    a:hover {
                        color: #8400ff;
                    }

                }

                .title {
                    text-align: center;
                    flex-grow: 1;

                    h1 {
                        font-family: 'Montserrat', sans-serif;
                        font-size: 42px;
                        font-weight: 900;
                        margin-top: 0px;
                        margin-bottom: 0px;
                        margin-left: -12px;
                        color: #000000;
                        text-transform: uppercase;
                        text-shadow: -1px -1px 0px #ffffff, 1px 1px 0px #606060;
                        opacity: 0.2;
                    }

                }

                .timetable {
                    font-size: 11px;

                    th {
                        text-align: right;
                        padding: 0 5px 0 0;
                        color: var(--fm-color);
                        font-weight: normal;
                        text-transform: capitalize;
                    }

                    td {
                        padding: 0;
                        color: var(--fg-color);
                        font-weight: bold;
                    }

                    th:not(:first-child) {
                        padding-left: 20px;
                    }

                    tr:not(:last-child) td {
                        color: var(--mt-color);
                        font-weight: normal;
                    }
                }
            }

            .dumpmain {
                display: flex;
                flex-direction: row;
                padding: var(--elem-padding);
                overflow: auto;
            }

            .dumpdata,
            .dumplog {
                margin: var(--elem-margin);
                line-height: 1.4;
                font-family: 'Montserrat', sans-serif;
                flex-grow: 1;

                .hdr {
                    font-size: .7rem;
                    font-weight: 100;
                    color: var(--mt-color);
                    text-align: right;
                    margin: 0 var(--dump-radius);

                    em {
                        font-style: normal;
                        color: var(--fm-color);
                    }
                }

                .data {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 0.8rem;
                    padding: var(--elem-padding);
                    color: var(--fg-color);
                    background-color: var(--bg-color);
                    border: 1px solid #666666;
                    border-radius: var(--dump-radius);
                    white-space: pre-wrap;
                    flex-grow: 1;
                }

                .log {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 0.8rem;

                    .entry {
                        padding: 0.5rem;
                        border-bottom: 1px solid #333;
                    }
                }
            }
        }

        @media only screen and (max-width: 767px) {
            .errorpage .status h2 {
                font-size: 24px;
            }
        }

        @media only screen and (max-width: 480px) {
            .errorpage .status h1 {
                font-size: 182px;
            }
        }
    </style>
</head>

<body>
    <div id="dumppage">
        <div class="dumpheader">
            <div class="actions"><a onClick="document.location.reload();">Reload</a></div>
            <div class="title">
                <h1>Hazaar Dump</h1>
            </div>
            <table class="timetable">
                {foreach from=$time item=item key=key}
                    <tr>
                        <th>{$key|capitalize}</th>
                        <td>{$item|string_format:"%.2f"}ms</td>
                    </tr>
                {/foreach}
            </table>
        </div>
        <div class="dumpmain">
            {if $data !== null}<div class="dumpdata">
                    <div class="hdr">
                        Dumping <em>{$data|type}</em> data from
                        <em>{$class}::{$function}</em> on line <em>#{$line}</em> of file <em>{$file}</em>
                    </div>
                    <div class="data">{$data|print}</div>
                </div>
            {/if}
            {if $log} <div class="dumplog">
                    <div class="hdr">
                        Log entries
                    </div>
                    <div class="log">
                        {foreach from=$log item=log}
                            {assign var="millis" value=round(($log['time']-floor($log['time']))*1000000)}
                            <div class="entry">{$log.time|date_format:"%Y-%m-%d %H:%M:%S"}.{$millis} - {$log.data|print}</div>
                        {/foreach}
                    </div>
                </div>
            {/if}
        </div>
</body>

</html>