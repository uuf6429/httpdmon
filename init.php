<?php

// ensure we have booted up
if (!defined('VERSION')) {
    require_once('boot.php');
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
