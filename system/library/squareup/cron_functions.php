<?php

function squareup_validate() {
    if (!getenv("SQUARE_CRON")) {
        die("Not in Command Line." . PHP_EOL);
    }
}

function squareup_chdir($current_dir) {
    $root_dir = dirname(dirname(dirname($current_dir)));

    chdir($root_dir);

    return $root_dir;
}

function squareup_init($current_dir) {
    // Validate environment
    squareup_validate();

    // Set up default server vars
    $_SERVER["HTTP_HOST"] = getenv("CUSTOM_SERVER_NAME");
    $_SERVER["SERVER_NAME"] = getenv("CUSTOM_SERVER_NAME");
    $_SERVER["SERVER_PORT"] = getenv("CUSTOM_SERVER_PORT");
    $_GET['route'] = getenv("SQUARE_ROUTE");

    putenv("SERVER_NAME=" . $_SERVER["SERVER_NAME"]);

    define('SQUAREUP_ROUTE', getenv("SQUARE_ROUTE"));

    // Change root dir
    $root_dir = squareup_chdir($current_dir);

    if (file_exists($root_dir . '/index.php')) {
        return $root_dir . '/index.php';
    }
}