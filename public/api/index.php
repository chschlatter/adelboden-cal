<?php
define('CAL_ROOT', dirname(__DIR__, 2));

require CAL_ROOT . '/vendor/autoload.php';
require CAL_ROOT . '/logger.php';

$dotenv = Dotenv\Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

$_GET['u'] = $_GET['u'] ?? '/';

$request = new Bullet\Request();
$app = new Bullet\App(array(
    'template.cfg' => array('path' => __DIR__ . '/templates/')
));

require CAL_ROOT . '/common.php';
require CAL_ROOT . '/EventMapper.php';
require CAL_ROOT . '/UserMapper.php';

$db = new SQLite3(CAL_ROOT . '/' . $_ENV['DB_FILE']);
$db->busyTimeout(5000);
$db->exec("CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    start TIMESTAMP NOT NULL,
    end TIMESTAMP NOT NULL,
    end_inclusive TIMESTAMP NOT NULL)");

$app->path(array('/', 'index'), function($req) use($app) {
    $app->get(function($req) use($app) {
        $app->format('html', function($req) use($app) {
            return $app->template('openapi');
        });
    });
});

// PATH /users
$app->path('users', function($req) use ($app, $db) {
    $users = new UserMapper($db);

    // PATH /users/login
    $app->path('login', function($req) use ($app, $users) {

        // POST (login with user_name & password in request body)
        $app->post(function($req) use ($app, $users) {
            $user = $req->json();
            if (!isset($user['name'])) {
                return $app->response(400, ['message' => 'No user name provided.']);
            }
            $users->authenticate($user, $app->response());
            
            return $app->response();
        });
    });
});

$app->path('events', function($req) use ($app, $db) {
    $events = new EventMapper($db);

    // populate $event array from JSON body, needed for POST and PUT methods
    $event = null;
    if ($req->isPost() or $req->isPut()) {
        $event = $req->json();
        if (!isset($event['title'], $event['start'], $event['end'])) {
            return $app->response(400, ['message' => 'Could not parse HTTP body.']);
        }
        $end_date_exclusive = new DateTimeImmutable($event['end']);
        $event['end_inclusive'] = 
            $end_date_exclusive->modify('-1 day')->format('Y-m-d');
        $event['id'] = $event['id'] ?? 0;
    }

    // get events: GET /event ; optional URL params 'start', 'end'
    $app->get(function ($req) use ($app, $events) {
        $range['start'] = $req->get('start');
        $range['end'] = $req->get('end');
        if ($range['start'] === null or $range['end'] === null) $range = null; 
        $events->getEvents($app->response());
        return $app->response();
    });

    // create event: POST /event ; body = JSON(event)
    $app->post(function ($req) use ($app, $events, $event) {
        $events->createEvent($event, $app->response());
        return $app->response();
    });

    // /events/{id}
    $app->param('int', function ($req, $event_id) use ($app, $events, $event) {

        // update event: PUT /event/{id} ; body = JSON(event)
        $app->put(function ($req) use ($app, $event_id, $events, $event) {
            $event['id'] = $event_id;
            $events->updateEvent($event, $app->response());
            return $app->response();
        });

        // delete event: DELETE /event/{id}
        $app->delete(function ($req) use ($app, $event_id, $events) {
            $events->deleteEvent($event_id, $app->response());
            return $app->response();
        });
    });
});

$response = $app->run($request);
// $result_str = $app->dump($result);
// Log::info('response: ' . $result_str);
// echo $app->dump($result);
$response->send();

// $app->run($request)->send();
