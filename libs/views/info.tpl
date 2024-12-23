<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <title>Hazaar Info</title>

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
                color: #fff;
                margin: 50px 0;

                .content {
                    max-width: 80%;
                    margin: auto;
                }

                .muted {
                    color: #666;
                    font-size: 0.9rem;
                }

                .small {
                    color: var(--fg-error);
                    font-size: 0.8rem;
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
            <div class="status status-200">
                <h1>{$version}</h1>
                <h2>Hazaar</h2>
            </div>
            {if $application->config['php']['display_errors'] == true}
                <div class="errormessage">
                    <p class="small">Loaded in {$time.total|string_format:"%.2f"}ms</p>
                </div>
            {/if}
        </div>
    </div>
</body>

</html>