<?php

// define some base constants
define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// define autoloader if not loaded
if (!class_exists('HttpdMon')) {
    function __autoload($class)
    {
        require_once('src/' . $class . '.php');
    }
}

// wrap the console
$con = new Console();

// do error handling
$err = new ErrorHandler($con);
$err->Attach();

// load configuration
$cfg = new Config(glob('conf.d/*.php'));

// run application
$app = new HttpdMon($cfg, $con);
$app->Run();
