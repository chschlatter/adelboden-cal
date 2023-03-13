<?php

define('CAL_ROOT', dirname(__DIR__, 1));

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

require CAL_ROOT . '/ApiRequest.php';
require CAL_ROOT . '/EventMapper.php';

$dotenv = Dotenv\Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

// fwrite(STDERR, print_r(self::$init_events, true));

class CalApiTest extends TestCase
{
    private static $client;
    private static $validator;
    private static $events;

    private static $init_events = [
            ['start' => '2022-01-30', 'end' => '2022-02-03'],
            ['start' => '2022-02-04', 'end' => '2022-02-06']
    ];

    protected function jsonRequest(string $method, 
                                   string $path, 
                                   string $username = '',
                                   array $guzzle_req_options = []): GuzzleHttp\Psr7\Response
    {
        if ($username) {
            $token = ApiRequest::createCookieToken($username);
            $guzzle_req_options['headers'] = array_merge($guzzle_req_options['headers'] ?? [], 
                                                         ['Cookie' => "token=$token"]);
        }

        $response = self::$client->request($method, $path, $guzzle_req_options);

        // validate response against OpenApi spec
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $operation = new \League\OpenAPIValidation\PSR7\OperationAddress($path, strtolower($method));
        self::$validator->validate($operation, $response);
        
        return $response;
    }

    protected function createEvent(array $dates, string $event_title): GuzzleHttp\Psr7\Response
    {
        $event = ['title' => $event_title, 
                  'start' => $dates[0], 
                  'end'   => $dates[1]
                 ];
        return $this->jsonRequest('POST', 'events', 'Christian', ['json' => $event]);
    }

    protected function createEvents(array $events_dates, string $event_title): array
    {
        $events = array();
        foreach ($events_dates as $dates) {
            $response = $this->createEvent($dates, $event_title);
            if ($response->getStatusCode() != 201) {
                throw new Exception("createEvents()\n" .
                                    "  StatusCode: " . $response->getStatusCode() . "\n" .
                                    "  Body: " . $response->getBody());
            }
            $events[] = json_decode($response->getBody());
        }
        return $events;
    }

    protected function deleteEventsBefore(string $before): void
    {
        $response = $this->jsonRequest('DELETE', 'events', 'admin', ['query' => ['before' => $before]]);
        if ($response->getStatusCode() != 200) {
            throw new Exception('deleteEvents() failed');
        }
    }

    protected function createEventOverlap(array $dates, string $title, string $type): void
    {
        $response = $this->createEvent($dates, $title);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('event-010', $response_body->code);

        switch ($type) {
            case 'overlap_start':
                $this->assertTrue(
                    $response_body->overlap_start == 1 &&
                    !isset($response_body->overlap_end),
                    'overlap_start failed');
                break;
            case 'overlap_end':
                $this->assertTrue(
                    $response_body->overlap_end == 1 &&
                    !isset($response_body->overlap_start),
                    'overlap_end failed');
                break;
            case 'overlap_both':
                $this->assertTrue(
                    $response_body->overlap_start == 1 &&
                    $response_body->overlap_end == 1,
                    'overlap_both failed');
                break;
            default:
                throw new Exception('createEventOverlap: unknown type [' . $type . ']');
        }
    }

    public static function setUpBeforeClass(): void
    {
        // create OpenAPI validator
        $yamlFile = CAL_ROOT . '/public/api/cal.openapi.yaml';
        self::$validator = (new \League\OpenAPIValidation\PSR7\ValidatorBuilder)
                           ->fromYamlFile($yamlFile)
                           ->getResponseValidator();

        // create Guzzle HTTP client
        self::$client = new Client([
            'base_uri' => 'http://localhost/api/',
            'http_errors' => false
        ]);
    }


    #[TestDox('Check if /api/ is alive')]
    public function testGetStatusCode()
    {
        $response = $this->jsonRequest('GET', 'events', 'Christian');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCookieAuthentication()
    {
        $response = $this->jsonRequest('GET', 'events');
        $this->assertEquals(401, $response->getStatusCode());

        $response = $this->jsonRequest('GET', 'events', 'Christian');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetEvents()
    {
        $events_dates = [['2022-01-30', '2022-02-03'],
                         ['2022-02-04', '2022-02-06']
                        ];

        $this->deleteEventsBefore('2023-01-01');
        $events_created = $this->createEvents($events_dates, 'Christian');

        $response = $this->jsonRequest('GET', 'events', 'Christian', 
                                      ['query' => ['start' => '2022-01-01', 'end' => '2022-12-31']]);
        $events_got = json_decode($response->getBody());
        $this->assertEquals($events_created, $events_got);  
    }

    public function testCreateEvent()
    {
        $this->deleteEventsBefore('2023-01-01');

        // incomplete event parameters
        $event = ['start' => '2022-01-30'];
        $response = $this->jsonRequest('POST', 'events', 'Christian',
                                       ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('api-030', $response_body->code);

        // event title != username
        $event = ['title' => 'test', 
                  'start' => '2022-01-03', 
                  'end' => '2022-01-04'];
        $response = $this->jsonRequest('POST', 'events', 'Christian',
                                       ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('api-020', $response_body->code);

        // create event
        $event = ['title' => 'Christian', 
                  'start' => '2022-01-03', 
                  'end' => '2022-01-04'];
        $response = $this->jsonRequest('POST', 'events', 'Christian',
                                       ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testEventOverlap()
    {
        $events_dates = [['2022-01-30', '2022-02-03'],
                         ['2022-02-04', '2022-02-06']
                        ];

        $this->deleteEventsBefore('2023-01-01');
        $this->createEvents($events_dates, 'Christian');

        // overlap_start
        $this->createEventOverlap(['2022-02-01', '2022-02-05'], 
                                  'Christian',
                                  'overlap_start');
    }

}
