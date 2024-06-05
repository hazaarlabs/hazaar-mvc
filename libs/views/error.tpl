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
            background: #030005;
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
                z-index: -1;

                h1 {
                    font-family: 'Montserrat', sans-serif;
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    -webkit-transform: translate(-50%, -50%);
                    -ms-transform: translate(-50%, -50%);
                    transform: translate(-50%, -50%);
                    font-size: 224px;
                    font-weight: 900;
                    margin-top: 0px;
                    margin-bottom: 0px;
                    margin-left: -12px;
                    color: #030005;
                    text-transform: uppercase;
                    text-shadow: -1px -1px 0px #8400ff, 1px 1px 0px #ff005a;
                    letter-spacing: -20px;
                }


                h2 {
                    font-family: 'Montserrat', sans-serif;
                    position: absolute;
                    left: 0;
                    right: 0;
                    top: 110px;
                    font-size: 42px;
                    font-weight: 700;
                    color: #fff;
                    text-transform: uppercase;
                    text-shadow: 0px 2px 0px #8400ff;
                    letter-spacing: 13px;
                    margin: 0;
                }

            }

            a {
                font-family: 'Montserrat', sans-serif;
                display: inline-block;
                text-transform: uppercase;
                color: #ff005a;
                text-decoration: none;
                border: 2px solid;
                background: transparent;
                padding: 10px 40px;
                font-size: 14px;
                font-weight: 700;
                -webkit-transition: 0.2s all;
                transition: 0.2s all;
            }

            a:hover {
                color: #8400ff;
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

                .big {
                    font-size: 1.2rem;
                }

                .small {
                    color: #ff005a;
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
            <div class="status status-{$code}">
                <h1>{$code}</h1>
                <h2>{$status}</h2>
            </div>
            {if $application->config['php']['display_errors'] == true}
                <div class="errormessage">
                    <p class="muted big">{$err.class}</p>
                    <p class="content">{$err.message}</p>
                    <p class="muted">{$err.file} (#{$err.line})</p>
                    <p class="small">Loaded in {$time|string_format:"%.2f"}ms</p>
                </div>
            {/if}
            <a href="{url '/'}">Homepage</a>
        </div>
    </div>
</body>

</html>