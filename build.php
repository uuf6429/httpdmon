<?php

define('BUILD_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'build');
define('BUILD_FILE', BUILD_PATH . DIRECTORY_SEPARATOR . 'httpdmon.php');
define('SRC_SEARCH_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . '*.php');

echo 'Cleaning up...' . PHP_EOL;
if (file_exists(BUILD_FILE)) {
    unlink(BUILD_FILE);
}

echo 'Preparing...' . PHP_EOL;
passthru('phpcbf --standard=PSR2 ' . __DIR__);
if (!is_dir(BUILD_PATH)) {
    mkdir(BUILD_PATH);
}

$content = '<?php' . PHP_EOL;

echo 'Building...' . PHP_EOL;
foreach (array_merge(
    array('boot.php'),
    glob(SRC_SEARCH_PATH),
    array('init.php')
) as $file) {
    echo 'Adding [' . basename($file) . ']...' . PHP_EOL;
    $content .= PHP_EOL
        . '### ' .basename($file) . PHP_EOL
        . PHP_EOL
        . trim(preg_replace('/<\\?php/', '', file_get_contents($file), 1)) . PHP_EOL
    ;
}

$content = implode(PHP_EOL, explode("\r", str_replace(array("\r\n", "\n"), "\r", $content)));

echo 'Saving...' . PHP_EOL;
file_put_contents(BUILD_FILE, $content);

echo 'Finished.' . PHP_EOL;
