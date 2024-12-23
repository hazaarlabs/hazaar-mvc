<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="em_AU">

<head>

    <base href="<?php echo $this->application->url(); ?>" target="_top" />

    <meta http-equiv="content-type" content="text/html; charset=utf-8" />

    <title>
        {$application->config->app['name']}
    </title>

    <link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="{url css/main.css}" />
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand"
                href="{url}">
                {$application->config['app']['name']}
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive"
                aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarResponsive">
                <ul class="navbar-nav">
                    <li class="nav-item active">
                        <a href="{url}"
                            class="nav-link">Home</a>
                    </li>
                    <li class="nav-item">
                        <a href="{url test}"
                            class="nav-link">Test</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="http://example.com" id="dropdown01"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Help</a>
                        <div class="dropdown-menu" aria-labelledby="dropdown01">
                            <h6 class="dropdown-header">Documentation</h6>
                            <a href="http://mvc.hazaar.io" class="dropdown-item" target="_blank">Hazaar Website</a>
                            <a href="http://scroly.io/hazaarmvc" class="dropdown-item" target="_blank">Online
                                Documentation</a>
                            <a href="http://scroly.io/hazaarmvc/latest/api" class="dropdown-item" target="_blank">API
                                Reference</a>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header">Support</h6>
                            <a href="http://git.hazaarlabs.com/hazaar/hazaar-mvc/issues" class="dropdown-item"
                                target="_blank">Support Issues</a>
                            <a href="http://git.hazaarlabs.com/hazaar/hazaar-mvc/issues/new" class="dropdown-item"
                                target="_blank">Create Issue</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main role="main">{layout}</main>

    <footer class="bd-footer text-muted">
        <p class="float-right py-4 px-5">
        </p>
        <div class="container-fluid p-3 p-md-5">
            <ul class="bd-footer-links">
                <li>
                    <a href="https://git.hazaarlabs.com/hazaar/hazaar-mvc">GitLab</a>
                </li>
                <li>
                    <a href="https://facebook.com/hazaarmvc">Facebook</a>
                </li>
                <li>
                    <a href="https://twitter.com/hazaarmvc">Twitter</a>
                </li>
                <li>
                    <a href="http://hazaarmvc.com/docs/examples">Examples</a>
                </li>
                <li>
                    <a href="http://hazaarmvc.com/about">About</a>
                </li>

            </ul>
            <p>Copyright &copy; Hazaar Labs - 2017</p>

        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>