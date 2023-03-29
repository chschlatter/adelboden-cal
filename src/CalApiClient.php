<?php
declare(strict_types=1);

namespace CalApi;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use CalApi\IAM;
use Exception;


class CalApiClient
{
    private Client $client;
    private string $admin_pwd;
    private array $transactions;
    public $debug_buffer; // PHP resources have no type (?)

    public function __construct(string $base_uri, string $admin_pwd)
    {
        $this->transactions = [];
        $history = Middleware::history($this->transactions);

        $handlerStack = HandlerStack::create();
        $handlerStack->push($history);

        $this->client = new Client([
            'handler' => $handlerStack,
            'base_uri' => $base_uri,
            'http_errors' => false
        ]);
        $this->admin_pwd = $admin_pwd;
    }

    public function sendRequest(string $method,
                                string $path,
                                array $json_body = [],
                                string $username = '',
                                array  $guzzle_req_options = []): Response
    {
        if ($username) {
            $token = IAM::createCookieTokenPwd($username, 
                                               $this->admin_pwd);
            $guzzle_req_options['headers'] = 
                array_merge($guzzle_req_options['headers'] ?? [], 
                            ['Cookie' => "token=$token"]);
        }

        if ($json_body) {
            $guzzle_req_options['json'] = $json_body;
        }

        return $this->client->request($method, $path, $guzzle_req_options);
    }

    public function makeAdminRequest(string $method,
                                     string $path,
                                     array $body = []): ?array
    {
        $options = [];
        if ($_ENV['SHELL_VERBOSITY'] > 2) {
            $this->debug_buffer = fopen('php://memory', 'r+');
            $options = ['debug' => $this->debug_buffer];
        }
        $response = $this->sendRequest($method, $path, $body, 'admin', $options);
        if ($response->getStatusCode() > 299) {
            throw new Exception('API request failed with HTTP response code ' . 
                                $response->getStatusCode() . "\n" .
                                (string) $response->getBody());
        }

        return json_decode((string) $response->getBody(), true);
    }

    public function getTransactionsDebug(): array
    {
        $out = [];
        foreach ($this->transactions as $transaction) {
            $entry['request'] = self::psr7RequestDebug($transaction['request']);
            $entry['response'] = null;
            if ($transaction['response']) {
                $entry['response'] = self::psr7ResponseDebug($transaction['response']);
            }
            $out[] = $entry;
        }

        return $out;
    }

    public static function psr7MessageDebug(MessageInterface $message): array
    {
        $out = [];
        foreach ($message->getHeaders() as $name => $values) {
            $out[] = $name . ": " . implode(", ", $values);
        }

        $body = (string) $message->getBody();
        if ($body) {
            $out[] = '';
            $out[] = $body;
        }

        return $out;
    }

    public static function psr7RequestDebug(RequestInterface $request): array
    {
        $out[] = $request->getMethod() . ' ' . $request->getRequestTarget();
        $out = array_merge($out, self::psr7MessageDebug($request));

        return $out;
    }

    public static function psr7ResponseDebug(ResponseInterface $response): array
    {
        $out[] = (string) $response->getStatusCode() . ' ' . $response->getReasonPhrase();
        $out = array_merge($out, self::psr7MessageDebug($response));

        return $out;
    }


}