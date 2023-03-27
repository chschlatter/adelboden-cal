<?php

namespace CalApi\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Routing\RouteContext;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\PSR7\OperationAddress;

use CalApi\Log;


class ValidatorMiddleware implements MiddlewareInterface
{
    private $validator = null;

    public function __construct(string $spec_file)
    {
        $this->validator = (new ValidatorBuilder)
                           ->fromYaml(file_get_contents($spec_file))
                           ->getRoutedRequestValidator();
    }

    public function process(ServerRequestInterface $request, 
                            RequestHandlerInterface $handler): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $pattern = $route->getPattern();

        $address = new OperationAddress($pattern, strtolower($request->getMethod()));
        $this->validator->validate($address, $request);

        return $handler->handle($request);
    }
}
