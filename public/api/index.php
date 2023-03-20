<?php

require '../../init.php';
require CAL_ROOT . '/ApiRequest.php';
require CAL_ROOT . '/EventMapper.php';
require CAL_ROOT . '/UserMapper.php';


$_GET['u'] = $_GET['u'] ?? '/';

$request = new ApiRequest();
$app = new Bullet\App(array(
    'template.cfg' => array('path' => __DIR__ . '/templates/')
));

require CAL_ROOT . '/common.php';

$app->path(array('/', 'index'), function($req) use($app) {
    $app->get(function($req) use($app) {
        $app->format('html', function($req) use($app) {
            return $app->template('openapi');
        });
    });
});

$request->userMapper(new UserMapper($db));

// PATH /users
$app->path('users', function($req) use ($app) {

    // get users: GET /users
    $app->get(function ($req) use ($app) {
        try {
            $req->auth('admin');
            return $app->response(200, $req->userMapper()->getUsers());
        } catch (ApiException $e) {
            return $app->response($e->getStatus(), $e->getResponse());
        }
    });

    // PATH /users/login
    $app->path('login', function($req) use ($app) {

        // POST (login with user_name & password in request body)
        $app->post(function($req) use ($app) {
            try {
                $user = $req->validateUserJson();
                $req->loginAuth($user);
                return $app->response(200, []);
            } catch (ApiException $e) {
                return $app->response($e->getStatus(), $e->getResponse());
            }
        });
    });
});

$app->path('events', function($req) use ($app, $db) {
    $events = new EventMapper($db);

    // get events: GET /event ; optional URL params 'start', 'end'
    $app->get(function ($req) use ($app, $events) {
        try {
            $req->auth();
            $range = $req->validateGetEvents();
            return $app->response(200, $events->getEvents($range));

        } catch (ApiException $e) {
            return $app->response($e->getStatus(), $e->getResponse());
        }
    });

    // create event: POST /events ; body = JSON(event)
    $app->post(function ($req) use ($app, $events) {
        try {
            $event = $req->validateEvent();
            $req->auth('admin', $event['title']);
            $event['id'] = 0;
            $event = $events->createEvent($event);
        } catch (ApiException $e) {
            return $app->response($e->getStatus(), $e->getResponse());
        }
        return $app->response(201, $event);
    });

    // delete events: DELETE /events ; URL params 'before'
    $app->delete(function ($req) use ($app, $events) {
        try {
            $req->auth('admin');
            $delete_params = $req->validateEventsDelete();
            $events->deleteEvents($delete_params);
        } catch (ApiException $e) {
            return $app->response($e->getStatus(), $e->getResponse());
        }
        return $app->response(200, []);
    });

    // /events/{id}
    $app->param('int', function ($req, $event_id) use ($app, $events) {

        // update event: PUT /event/{id} ; body = JSON(event)
        $app->put(function ($req) use ($app, $event_id, $events) {
            try {
                $db_event_title = $events->getEventTitle($event_id);
                $req->auth('admin', $db_event_title);
                $event = $req->validateEvent();

                // normal users shall not change event title
                if ($req->getUsername() != 'admin' && 
                    $db_event_title != $event['title']) {
                    throw new ApiException('api-020');
                };

                $event['id'] = $event_id;
                $event = $events->updateEvent($event);
            } catch (ApiException $e) {
                return $app->response($e->getStatus(), $e->getResponse());
            }
            return $app->response(200, $event);
        });

        // delete event: DELETE /event/{id}
        $app->delete(function ($req) use ($app, $event_id, $events) {
            try {
                $req->auth('admin', $events->getEventTitle($event_id));
                $events->deleteEvent($event_id);
            } catch (ApiException $e) {
                return $app->response($e->getStatus(), $e->getResponse());
            }
            return $app->response(200, []);
        });
    });
});

$app->run($request)->send();

