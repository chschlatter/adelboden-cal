<?php

class ApiException extends Exception
{
    protected $api_error_code;

    private $http_status = 500;
    private $api_message = '';
    private $api_code = 'api-999';
    private $api_properties = [];

    private $api_errors = array(
        'api-010'   => ['message' => 'Database error',
                        'status'  => 500],
        'api-020'   => ['message' => 'Not authorized',
                        'status'  => 401],
        'api-021'   => ['message' => 'Needs admin rights',
                        'status'  => 401],
        'api-030'   => ['message' => 'Request body validation error',
                        'status'  => 400],
        'api-031'   => ['message' => 'Wrong request query paramaters provided',
                        'status'  => 400],
        'api-999'   => ['message' => 'Error',
                        'status'  => 500],

        'auth-010'  => ['message' => 'Wrong password',
                        'status'  => 400],
        'auth-011'  => ['message' => 'User not found',
                        'status'  => 400],

        'event-010' => ['message' => 'Overlap found',
                        'status'  => 422 ],
        'event-011' => ['message' => 'Could not find event in database',
                        'status'  => 404],
        'event-020' => ['message' => 'Wrong date provided',
                        'status'  => 400],
        'event-021' => ['message' => "Only one of 'from' and 'end' paramaters provided",
                        'status'  => 400]
    );

    public function __construct($api_error_code, 
                                $api_properties = [], 
                                $message = '', 
                                $code = 0, 
                                Throwable $previous = null)
    {
        if (!array_key_exists($api_error_code, $this->api_errors)) {
            Log::warning(__CLASS__ . ': unknown $error_code [' . $error_code . '].');
            $this->api_error_code = 'api-999';
        }

        $this->http_status = $this->api_errors[$api_error_code]['status'];
        $this->api_message = $this->api_errors[$api_error_code]['message'];
        $this->api_code = $api_error_code;
        $this->api_properties = $api_properties;

        parent::__construct($message, $code, $previous);
    }

    public function getStatus(): int
    {
        return $this->http_status;
    }

    public function getApiMessage(): string
    {
        return $this->body_message;
    }

    public function getApiCode(): string
    {
        return $this->api_code;
    }

    public function getApiProperties(): array
    {
        return $this->api_properties;
    }

    public function getResponse(): array
    {
        $data = ['message' => $this->api_message,
                 'code' => $this->api_code];
        return array_merge($data, $this->api_properties);
    }

    public function getResponseJson(): string
    {
        return json_encode($this->getResponse());
    }
}