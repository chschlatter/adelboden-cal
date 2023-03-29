<?php

use Slim\Routing\RouteCollectorProxy;
use Slim\Factory\AppFactory;

use CalApi\Middleware\AuthMiddleware;
use CalApi\Middleware\ValidatorMiddleware;
use CalApi\IAM;
use CalApi\EventController;
use CalApi\UserController;
use CalApi\ApiErrorHandler;


$container = require __DIR__ . '/../../bootstrap.php';


$app = \DI\Bridge\Slim\Bridge::create($container);
$app->setBasePath(BASE_API_PATH);

$app->add(new ValidatorMiddleware(CAL_API . '/' . $_ENV['OPENAPI_FILE']));
$app->add(new AuthMiddleware($container->get(IAM::class)));
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$callableResolver = $app->getCallableResolver();
$responseFactory = $app->getResponseFactory();
$errorMiddleware->setDefaultErrorHandler(new ApiErrorHandler($callableResolver, $responseFactory));

$app->group('/users', function (RouteCollectorProxy $group) {

    // GET /users - get list of users
    $group->get('', [UserController::class, 'getUsers'])
        ->setName('get-users');

    $group->post('', [UserController::class, 'createUser'])
        ->setName('create-user');

    $group->delete('/{name}', [UserController::class, 'deleteUser'])
        ->setName('delete-user');

    // POST /users/login - login user and set cookie
    $group->post('/login', [UserController::class, 'login'])
        ->setName('users-login');
});

$app->group('/events', function (RouteCollectorProxy $group) {

    $group->get('', [EventController::class, 'getEvents'])
        ->setName('get-events');

    $group->post('', [EventController::class, 'createEvent'])
        ->setName('create-event');

    $group->put('/{id}', [EventController::class, 'updateEvent'])
        ->setName('update-event');

    $group->delete('/{id}', [EventController::class, 'deleteEvent'])
        ->setName('delete-event');

    $group->delete('', [EventController::class, 'deleteEvents'])
        ->setName('delete-events');
});

$app->run();