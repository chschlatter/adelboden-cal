<?php

require '../../init.php';
require CAL_ROOT . '/ApiResponse.php';
require CAL_ROOT . '/EventMapper.php';
require CAL_ROOT . '/UserMapper.php';


$_GET['u'] = $_GET['u'] ?? '/';

$request = new Bullet\Request();
$app = new Bullet\App(array(
    'template.cfg' => array('path' => __DIR__ . '/templates/')
));

require CAL_ROOT . '/common.php';

$db = new SQLite3(CAL_ROOT . '/' . $_ENV['DB_FILE']);
$db->busyTimeout(5000);
$db->exec("CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    start TIMESTAMP NOT NULL,
    end TIMESTAMP NOT NULL,
    end_inclusive TIMESTAMP NOT NULL)");


$app->response(new ApiResponse());


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
            if ($users->authenticate($user, $app->response())) {
                // generate token
                $username_base64 = base64_encode($user['name']);
                $signature = hash('sha256', $user['name'] . $_ENV['APP_ADMIN_PWD']);
                $token = $username_base64 . '.' . $signature;
                // $app->response()->content(array_merge($app->response()->content(), ['token' => $token]));
                // setcookie('token', $token, strtotime('+300 days'));
                // setcookie('token', $token, 0, '/');
                setcookie('token', $token, 
                          array('path' => '/',
                                'expires' => 0,
                                'httponly' => true,
                                'samesite' => 'strict'));
            }

            return $app->response();
        });

    });
    return false;
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
        if (!$app->response()->auth()) return $app->response()->error('api-020');

        $range['start'] = $req->get('start');
        $range['end'] = $req->get('end');
        if ($range['start'] === null or $range['end'] === null) $range = null; 
        $result = $events->getEvents($range);
        if ($result['success']) {
            return $app->response(200, $result['properties']);
        }
        return $app->response()->error($result['error_code'], $result['properties']);
    });

    // create event: POST /event ; body = JSON(event)
    $app->post(function ($req) use ($app, $events, $event) {
        if (!$app->response()->auth()) return $app->response()->error('api-020');

        $result = $events->createEvent($event);
        if ($result['success']) {
            return $app->response(201, $result['properties']);
        }
        return $app->response()->error($result['error_code'], $result['properties']);
    });

    // /events/{id}
    $app->param('int', function ($req, $event_id) use ($app, $events, $event) {

        // update event: PUT /event/{id} ; body = JSON(event)
        $app->put(function ($req) use ($app, $event_id, $events, $event) {
            if (!$app->response()->auth()) return $app->response()->error('api-020');

            $event['id'] = $event_id;
            $result = $events->updateEvent($event);
            if ($result['success']) {
                return $app->response(200, $result['properties']);
            };
            return $app->response()->error($result['error_code'], $result['properties']);
        });

        // delete event: DELETE /event/{id}
        $app->delete(function ($req) use ($app, $event_id, $events) {
            if (!$app->response()->auth()) return $app->response()->error('api-020');

            $result = $events->deleteEvent($event_id);
            if ($result['success']) {
                return $app->response(200, $result['properties']);
            }
            return $app->response()->error($result['error_code'], $result['properties']);
        });
    });
});

$response = $app->run($request);
// $result_str = $app->dump($result);
// Log::info('response: ' . $result_str);
// echo $app->dump($result);
$response->send();

// $app->run($request)->send();
