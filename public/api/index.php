<?php
define('CAL_ROOT', dirname(__DIR__, 2));

require CAL_ROOT . '/vendor/autoload.php';
require CAL_ROOT . '/logger.php';

$dotenv = Dotenv\Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

$request = new Bullet\Request();
$app = new Bullet\App();

require CAL_ROOT . '/common.php';
require CAL_ROOT . '/EventMapper.php';

$db = new SQLite3(CAL_ROOT . '/' . $_ENV['DB_FILE']);
$db->busyTimeout(5000);
$db->exec("CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    start TIMESTAMP NOT NULL,
    end TIMESTAMP NOT NULL,
    end_inclusive TIMESTAMP NOT NULL)");

$app->path('event', function($req) use ($app, $db) {
    $events = new EventMapper($db);

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

    // /event/{id}
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

$result = $app->run($request);
// $result_str = $app->dump($result);
// Log::info('response: ' . $result_str);
// echo $app->dump($result);
$result->send();

// $app->run($request)->send();
