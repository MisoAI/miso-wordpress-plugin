<?php

$_tests_dir = getenv('WP_TESTS_DIR') ?: dirname(__DIR__) . '/tests-wp/lib';

if (!file_exists("$_tests_dir/includes/functions.php")) {
    fwrite(STDERR, "Could not find $_tests_dir/includes/functions.php — run bin/install-wp-tests.sh first." . PHP_EOL);
    exit(1);
}

require_once "$_tests_dir/includes/functions.php";

tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/miso-ai.php';
});

require "$_tests_dir/includes/bootstrap.php";
