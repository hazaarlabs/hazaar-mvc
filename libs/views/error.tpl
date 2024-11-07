<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <title>{$code} - {$status}</title>

    <!-- Google font -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,700,900" rel="stylesheet">

    <style>
        :root {
            --bg-color: #212A37;
            --fg-color: #c6d3e4;
            --fg-muted: #666;
            --fg-error: #a21c1c;
        }

        * {
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
        }

        body {
            padding: 0;
            margin: 0;
        }

        #errorpage {
            position: relative;
            height: 100vh;
            background: var(--bg-color);
        }

        #errorpage .errorpage {
            position: absolute;
            left: 50%;
            top: 50%;
            -webkit-transform: translate(-50%, -50%);
            -ms-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
        }

        .errorpage {
            width: 100%;
            line-height: 1.4;
            text-align: center;
            letter-spacing: 1px;

            .status {
                position: relative;
                height: 180px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;

                h1 {
                    font-family: 'Montserrat', sans-serif;
                    position: absolute;
                    font-size: 224px;
                    font-weight: 900;
                    margin: 0;
                    color: #000000;
                    text-transform: uppercase;
                    text-shadow: -1px -1px 0px #ffffff, 1px 1px 0px #606060;
                    letter-spacing: -20px;
                    z-index: 1;
                    opacity: 0.1;
                }

                h2 {
                    font-family: 'Montserrat', sans-serif;
                    position: relative;
                    text-transform: uppercase;
                    letter-spacing: 13px;
                    font-size: 42px;
                    font-weight: 700;
                    color: var(--fg-color);
                    z-index: 2;
                }
            }

            .errormessage {
                font-family: 'Montserrat', sans-serif;
                color: var(--fg-color);
                margin: 50px 0;

                .content {
                    max-width: 80%;
                    margin: auto;
                }

                .muted {
                    color: var(--fg-muted);
                    font-size: 0.9rem;
                }

                .big {
                    font-size: 1.2rem;
                }

                .small {
                    color: var(--fg-error);
                    font-size: 0.8rem;
                }
            }

            .timetable {
                margin: auto;
                margin-top: 20px;
                font-size: 11px;

                th {
                    text-align: right;
                    padding-right: 5px;
                    color: var(--fg-error);
                    font-weight: bold;
                    text-transform: capitalize;
                }

                th:not(:first-child) {
                    padding-left: 20px;
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
    <div id="errorpage">
        <div class="errorpage">
            <div class="status status-{$code}">
                <h1>{$code}</h1>
                <h2>{$status}</h2>
            </div>
            {if $application->config['php']['display_errors'] == true}
                <div class="errormessage">
                    <p class="muted big">{$err.class}</p>
                    <p class="content">{$err.message}</p>
                    <p class="muted">{$err.file} (#{$err.line})</p>
                    <table class="timetable">
                        <tr>
                            {foreach from=$time item=item key=key}
                                <th>{$key|capitalize}</th>
                                <td>{$item|string_format:"%.2f"}ms</td>
                            {/foreach}
                        </tr>
                    </table>
                </div>
            {/if}
        </div>
    </div>
</body>

</html>