<?php

// define some base constants
define('VERSION', '2.0.10');
define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

// define our (very simplistic) autoloader
function __autoload($class)
{
    require_once('src/' . $class . '.php');
}
