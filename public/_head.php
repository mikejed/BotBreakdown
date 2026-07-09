<?php
if (!isset($allowCache) || $allowCache != true) {
    header('Expires: Thu, 1 Jan 1970 00:00:00 GMT');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0',false);
    header('Pragma: no-cache');
}

if ($currentPersonId == 0) {
    $loginRedirectUrl = substr(strstr($_SERVER['SCRIPT_URI'],$_SERVER['HTTP_HOST']),strlen($_SERVER['HTTP_HOST']));
    if ($_SERVER['QUERY_STRING'] != '') {
        $loginRedirectUrl = $loginRedirectUrl . '?' . $_SERVER['QUERY_STRING'];
    }

    // If the page requires you to be logged in and they're not, redirect to the login page. (Make sure to come back to the requested page once they're logged in).
    if( isset($authRequired) && $authRequired ) {
        header('Location: /login.php?returnurl=' . rawurlencode(rawurlencode( $loginRedirectUrl )), true, 302);
        exit;
    }
}

// log a page view.
logHistory("view", $_SERVER['REQUEST_URI']);

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Bot Breakdown<?php if( isset($pageTitle) ) { echo(" | " . e($pageTitle)); }?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <link href="/assets/fontawesome/css/fontawesome.css" rel="stylesheet" />
        <link href="/assets/fontawesome/css/brands.css" rel="stylesheet" />
        <link href="/assets/fontawesome/css/solid.css" rel="stylesheet" />
        <link href="/assets/css/themeOverrides.css" rel="stylesheet" />
        <script>
            var storedTheme = localStorage.getItem('theme');
            if (storedTheme == null || storedTheme.length == 0) {
                // no theme stored- get it from the browser
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    $('html').attr('data-bs-theme', 'dark');
                    localStorage.setItem('theme','dark');
                    storedTheme = 'dark';
                }
                else {
                    localStorage.setItem('theme','light');
                    storedTheme = 'light';
                }
            }
            if (storedTheme === 'dark') {
                $('html').attr('data-bs-theme', 'dark');
            }

            function toggleDarkMode() {
                var currentTheme = $('html').attr('data-bs-theme');
                if (currentTheme === 'dark') {
                    $('html').removeAttr('data-bs-theme');
                    localStorage.setItem('theme','light');
                } else {
                    $('html').attr('data-bs-theme', 'dark');
                    localStorage.setItem('theme','dark');
                }
            }
        </script>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">
                    <img src="/assets/images/BotBreakdown-Icon.svg" style="height: 24px;">
                    Bot Breakdown
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="/myevents">My Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" aria-current="page" href="/blog">Blog</a>
                        </li>
                    </ul>

                    <a href="javascript:toggleDarkMode();" class="btn btn-default"><i class="fa-solid fa-cloud-sun"></i></a>
                    <ul class="navbar-nav" style="min-width: 160px;">
                        <?php if ($currentPersonId > 0) { ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle ms-0" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php echo $currentPersonName; ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/myaccount.php">Settings</a></li>
                                    <?php
                                    if ($isAdmin) {
                                        echo('<li><a class="dropdown-item" href="/admin">Admin Tools</a></li>');
                                    }
                                    ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/logout.php">Log out</a></li>
                                </ul>
                            </li>
                        <?php
                        } else {
                            echo('<li><a class="btn btn-outline-success" href="/login.php?returnurl=' . rawurlencode(rawurlencode( $loginRedirectUrl )) . '">Log in</a></li>');
                        } ?>
                    </ul>
                </div>
            </div>
        </nav>