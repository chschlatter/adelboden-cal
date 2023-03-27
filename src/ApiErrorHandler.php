<?php

namespace CalApi;

use Psr\Http\Message\ResponseInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Exception\HttpException;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;

use CalApi\ApiException;
use CalApi\Log;

class ApiErrorHandler extends ErrorHandler
{
    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        // print_r($exception);

        $code = 'api-999';
        $status = 500;
        // $message = 'An internal error has occurred while processing your request.';
        $message = $exception->getMessage();
        $prev = $exception->getPrevious();
        //Log::info('ErrorHandler prev name [' . $prev->name() . ']');

        if ($exception instanceof ValidationFailed) {
            $code = 'api-030';
            $status = 400;
            $message = $exception->getMessage();
        }

        if ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $message = $exception->getDescription();
        }

        if ($exception instanceof ApiException) {
            $body = $exception->getResponseJson();
            $status = $exception->getStatus();
        } else {
            $error = ['code' => $code, 'message' => $message];
            $body = json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($body);
        $response = $response->withHeader('Content-Type', 'application/json');

        return $response; 
    }
}