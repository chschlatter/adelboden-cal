<?php
// Setup defaults...
date_default_timezone_set('UTC');
error_reporting(-1); // Display ALL errors
ini_set('display_errors', '1');
ini_set("session.cookie_httponly", '1'); // Mitigate XSS javascript cookie attacks for browers that support it
ini_set("session.use_only_cookies", '1'); // Don't allow session_id in URLs

define('CAL_ENV', $request->env('CAL_ENV', 'development'));

// Production setting switch
if (CAL_ENV == 'production') {
    // Hide errors in production
    error_reporting(0);
    ini_set('display_errors', '0');
}

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

// Display exceptions with error and 500 status
$app->on('Exception', function(\Bullet\Request $request, \Bullet\Response $response, \Exception $e) use($app) {
    if ($request->format() === 'json') {
        $data = [
            'error' => str_replace('Exception', '', get_class($e)),
            'message' => $e->getMessage()
        ];

        // Debugging info for development ENV
        if (CAL_ENV !== 'production') {
            $data['file'] = $e->getFile();
            $data['line'] = $e->getLine();
            $data['trace'] = $e->getTrace();
        }

        $response->content($data);
    } 
    /* 
    else {
        $response->content($app->template('errors/exception', ['e' => $e])->content());
    }
    */

    if (CAL_ENV === 'production') {
        // An error happened in production. You should really let yourself know about it.
        // TODO: Email, log to file, or send to error-logging service like Sentry, Airbrake, etc.
    }
});
