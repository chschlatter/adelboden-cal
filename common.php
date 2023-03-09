<?php
// Setup defaults...
date_default_timezone_set('UTC');
error_reporting(-1); // Display ALL errors
ini_set('display_errors', '1');

define('CAL_ENV', $request->env('CAL_ENV', 'development'));

// Production setting switch
if (CAL_ENV == 'production') {
    // Hide errors in production
    error_reporting(0);
    ini_set('display_errors', '0');
}


$app->on(404, function(\Bullet\Request $request, \Bullet\Response $response) use ($app) {
    if ($request->format() === 'json') {
        if ($response->content() == $response->statusText(404)) {
            $response->content(json_encode(['message' => 'Not Found']));
        }
    }
});

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
