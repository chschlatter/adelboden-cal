<?php

define('CAL_ROOT', __DIR__);
define('CAL_PUBLIC', CAL_ROOT . '/public');
define('CAL_API', CAL_PUBLIC . '/api');

require CAL_ROOT . '/vendor/autoload.php';
require CAL_ROOT . '/logger.php';
require CAL_ROOT . '/ApiException.php';

$dotenv = Dotenv\Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

// Throw Exceptions for everything so we can see the errors
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    // Don't catch suppressed errors with '@' sign
    // @link http://stackoverflow.com/questions/7380782/error-supression-operator-and-set-error-handler
    $error_reporting = ini_get('error_reporting');
    if (!($error_reporting & $errno)) {
        return;
    }
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}
set_error_handler("exception_error_handler");