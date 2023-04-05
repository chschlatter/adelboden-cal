<?php

namespace CalApi\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Routing\RouteContext;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;

use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

use CalApi\IAM;
use CalApi\UserMapper;
use CalApi\ApiException;
use CalApi\Log;


class AuthMiddleware implements MiddlewareInterface
{
    private IAM $iam;

    #[CodeCoverageIgnore]
    public function __construct(IAM $iam)
    {
        $this->iam = $iam;
    }

    public function process(ServerRequestInterface $request, 
                            RequestHandlerInterface $handler): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        if (empty($route)) {
            throw new HttpNotFoundException($request);
        }
        $route_name = $route->getName();

        // no auth for user login
        if ($route_name == 'users-login') {
            return $handler->handle($request);
        }

        $cookies = $request->getCookieParams();
        if (false === ($username = $this->iam->verifyToken($cookies))) {
            throw new ApiException('api-020');
        }

        $access_permitted = false;
        switch ($route_name) {
            case 'get-events':
            case 'create-event':
            case 'update-event':
            case 'delete-event':
                $access_permitted = 
                    $this->iam->role($username, UserMapper::ADMIN | UserMapper::USER);
                break;
            case 'delete-events':
            case 'get-users':
            case 'create-user':
            case 'delete-user':
            case 'api-tests-coverage-report':
                $access_permitted = 
                    $this->iam->role($username, UserMapper::ADMIN);
                break;
        }
        if (!$access_permitted) {
            throw new ApiException('api-020');
        }

        $request = $request->withAttribute('username', $username);

        return $handler->handle($request);
    }
}