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
            --col-bg: #030005;
            --col-fg: #fff;
            --col-em: #ff005a;
            --col-mt: #999;
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
            background: var(--col-bg);
            height: 100vh;


            .dumpheader {
                background: var(--col-bg);
                display: flex;
                flex-direction: row;
                width: 100%;
                padding: calc(var(--elem-padding) / 2) var(--elem-padding);
                box-shadow: 0 4px 8px black;
                z-index: 1;

                .actions {
                    align-self: center;

                    a {
                        display: inline-block;
                        text-transform: uppercase;
                        color: var(--col-em);
                        text-decoration: none;
                        border: 2px solid;
                        background: transparent;
                        padding: 10px 40px;
                        font-size: 14px;
                        font-weight: 700;
                        -webkit-transition: 0.2s all;
                        transition: 0.2s all;
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
                        font-size: 42px;
                        font-weight: 900;
                        margin-top: 0px;
                        margin-bottom: 0px;
                        margin-left: -12px;
                        color: var(--col-bg);
                        text-transform: uppercase;
                        text-shadow: -1px -1px 0px #8400ff, 1px 1px 0px #ff005a;
                    }
                }

                .timetable {
                    font-size: x-small;

                    th {
                        text-align: right;
                        padding: 0 5px 0 0;
                        color: var(--col-em);
                        font-weight: normal;
                    }

                    td {
                        padding: 0;
                        color: var(--col-fg);
                        font-weight: bold;
                    }

                    th:not(:first-child) {
                        padding-left: 20px;
                    }

                    tr:not(:last-child) td {
                        color: var(--col-mt);
                        font-weight: normal;
                    }
                }
            }

            .dumpdata {
                margin: var(--elem-margin);
                line-height: 1.4;
                font-family: 'Montserrat', sans-serif;
                overflow-y: auto;



                .hdr {
                    font-size: .7rem;
                    font-weight: 100;
                    color: var(--col-mt);
                    text-align: right;
                    margin: 0 var(--dump-radius);

                    em {
                        font-style: normal;
                        color: var(--col-em);
                    }
                }

                .data {
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 0.8rem;
                    white-space: pre;
                    padding: var(--elem-padding);
                    color: var(--col-fg);
                    background-color: var(--col-bg);
                    border: 1px solid #333;
                    border-radius: var(--dump-radius);
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
        <div class="dumpdata">
            <div class="hdr"><em>{$class}::{$function}</em> on line <em>#{$line}</em> of file <em>{$file}</em></div>
            <div class="data">{$data|dump|escape:html}</div>
        </div>
    </div>
</body>

</html>