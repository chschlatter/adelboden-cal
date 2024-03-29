<?php

use Helmich\JsonAssert\JsonAssertions;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

use CalApi\CalApiClient;
use CalApi\Middleware\CoverageMiddleware;
use CalApi\IAM;

define('TEST_USER', 'Christian');


// fwrite(STDERR, print_r(self::$init_events, true));

class CalApiTest extends TestCase
{
    use JsonAssertions;

    private static $client;
    private static $validator;
    private static $events;

    private static $init_events = [
            ['start' => '2022-01-30', 'end' => '2022-02-03'],
            ['start' => '2022-02-04', 'end' => '2022-02-06']
    ];

    public static function setUpBeforeClass(): void
    {
        // create OpenAPI validator
        $yamlFile = CAL_ROOT . '/public/api/cal.openapi.yaml';
        self::$validator = (new \League\OpenAPIValidation\PSR7\ValidatorBuilder)
                           ->fromYamlFile($yamlFile)
                           ->getResponseValidator();

        self::$client = new CalApiClient('http://localhost/api/', 
                                        $_ENV['APP_ADMIN_PWD']);
    }

    protected function jsonRequest(string $method, 
                                   string $path, 
                                   string $username = '',
                                   array  $guzzle_req_options = []): GuzzleHttp\Psr7\Response
    {
        if (isset($_SERVER['COVERAGE']) && ($_SERVER['COVERAGE'] == true)) {

            // get calling function name
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
            $caller = $dbt[1]['function'] ?? 'CalApiTest';

            // add coverage header to trigger coverage on server side
            $guzzle_req_options = array_merge($guzzle_req_options,
                                   ['headers' => [CoverageMiddleware::HEADER => $caller]]);
        }

        $response = self::$client->sendRequest($method, $path, [], $username, $guzzle_req_options);

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
        return $this->jsonRequest('POST', 'events', TEST_USER, ['json' => $event]);
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

    protected function tearDown(): void
    {
        $this->deleteEventsBefore('2023-01-01');
    }


    #[TestDox('Check if /api/ is alive')]
    public function testGetStatusCode()
    {
        $response = $this->jsonRequest('GET', 'events', TEST_USER);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCookieAuthentication()
    {
        $response = $this->jsonRequest('GET', 'events');
        $this->assertEquals(401, $response->getStatusCode(), 'no username');

        $response = $this->jsonRequest('GET', 'events', bin2hex(random_bytes(5)));
        $this->assertEquals(401, $response->getStatusCode(), 'random username');

        $response = $this->jsonRequest('GET', 'events', 'admin');
        $this->assertEquals(200, $response->getStatusCode(), 'admin user');

        $response = $this->jsonRequest('GET', 'events', TEST_USER);
        $this->assertEquals(200, $response->getStatusCode(), 'test username');
    }

    
    
    public function testGetUsers()
    {
        // non-admin should not be allowed
        $response = $this->jsonRequest('GET', 'users', TEST_USER);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(401, $response->getStatusCode(), 'non-admin');

        // with admin user
        $response = $this->jsonRequest('GET', 'users', 'admin');
        $response_body = json_decode($response->getBody());
        $this->assertEquals(200, $response->getStatusCode(), 'get users');
    }

    public function testAddUser()
    {
        // user exists already
        $user = ['name' => TEST_USER];
        $response = $this->jsonRequest('POST', 'users', 'admin', ['json' => $user]);
        $this->assertEquals(400, $response->getStatusCode(), 'user already exists');

        // new user
        $username = 'testuser-' . bin2hex(random_bytes(5));
        $user = ['name' => $username];
        $response = $this->jsonRequest('POST', 'users', 'admin', ['json' => $user]);
        $this->assertEquals(201, $response->getStatusCode(), 'new user');

        // with admin user
        $response = $this->jsonRequest('GET', 'users', 'admin');
        $response_body = json_decode((string) $response->getBody());
        $this->assertContains($username, $response_body);
    }

    public function testDeleteUser()
    {
        // user doesn't exist
        $username = bin2hex(random_bytes(5));
        $response = $this->jsonRequest('DELETE', 'users/' . $username, 'admin');
        $this->assertEquals(400, $response->getStatusCode(), 'user does not exist');

        // delete existing user
        $username = 'testuser-' . bin2hex(random_bytes(5));
        $user = ['name' => $username];
        $response = $this->jsonRequest('POST', 'users', 'admin', ['json' => $user]);

        $response = $this->jsonRequest('DELETE', 'users/' . $username, 'admin');
        $this->assertEquals(200, $response->getStatusCode(), 'delete existing user');

        $response = $this->jsonRequest('GET', 'users', 'admin');
        $users = json_decode((string) $response->getBody());
        $this->assertNotContains($username, $users);

        // delete all test users
        foreach ($users as $username) {
            if (str_starts_with($username, 'testuser-')) {
                $response = $this->jsonRequest('DELETE', 'users/' . $username, 'admin');
                $this->assertEquals(200, $response->getStatusCode(), 'delete all test users');
            }
        }

    }


    public function testUserLogin()
    {
        // non-existing user
        $user = ['name' => bin2hex(random_bytes(5))];
        $response = $this->jsonRequest('POST', 
                                       'users/login',
                                       '',
                                       ['json' => $user]);
        $this->assertEquals(400, $response->getStatusCode(), 'non-existing user');

        // existing user & check cookie
        $user = ['name' => TEST_USER];
        $response = $this->jsonRequest('POST', 
                                       'users/login',
                                       '',
                                       ['json' => $user]);
        $this->assertEquals(200, $response->getStatusCode(), 'existing user - 200 OK');  
        $token = IAM::createCookieTokenPwd(TEST_USER, $_ENV['APP_ADMIN_PWD']);
        $cookie_header = urldecode($response->getHeaderLine('Set-Cookie'));
        $this->assertStringContainsString('token=' . $token, 
                                          $cookie_header,
                                          'existing user - correct token in cookie');

        // admin with wrong password
        $user = ['name' => 'admin', 'password' => bin2hex(random_bytes(5))];
        $response = $this->jsonRequest('POST', 
                                       'users/login',
                                       '',
                                       ['json' => $user]);
        $this->assertEquals(400, $response->getStatusCode(), 'admin with wrong password');

        // admin & check cookie
        $user = ['name' => 'admin', 'password' => $_ENV['APP_ADMIN_PWD']];
        $response = $this->jsonRequest('POST', 
                                       'users/login',
                                       '',
                                       ['json' => $user]);
        $this->assertEquals(200, $response->getStatusCode(), 'admin - 200 OK'); 
        $token = IAM::createCookieTokenPwd('admin', $_ENV['APP_ADMIN_PWD']);
        $cookie_header = urldecode($response->getHeaderLine('Set-Cookie'));
        $this->assertStringContainsString('token=' . $token, 
                                          $cookie_header,
                                          'admin - correct token in cookie');

    }


    /*
    public function testAddUser()
    {
        // non-admin should not be allowed
        $response = $this->jsonRequest('POST', 'users', TEST_USER, ['json' => ['name' => 'Nils']]);
        $this->assertEquals(401, $response->getStatusCode(), 'non-admin');

        // with admin user
        $response = $this->jsonRequest('POST', 'users', 'admin', ['json' => ['name' => 'Nils']]);
        $this->assertEquals(200, $response->getStatusCode(), 'add user');
    }
    */

    
    #[TestDox('CRUD event')]
    public function testCRUDEvent()
    {
        $this->deleteEventsBefore('2023-01-01');

        // create event
        $event = ['title' => TEST_USER, 
                  'start' => '2022-01-03', 
                  'end' => '2022-01-04'];
        $response = $this->jsonRequest('POST', 'events', TEST_USER, ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(201, $response->getStatusCode(), 'create event');
        $event['id'] = $response_body->id;
        $this->assertEquals((object) $event, $response_body, 'create event');

        // $this->assertJsonValueEquals((string) $response->getBody(), '$.title', TEST_USER);

        // update event
        $event_update = ['title' => TEST_USER, 
                         'start' => '2022-01-03', 
                         'end' => '2022-01-06'];
        $response = $this->jsonRequest('PUT', 'events/' . $event['id'], TEST_USER,
                                       ['json' => $event_update]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(200, $response->getStatusCode(), 'update event');
        $event_update['id'] = $event['id'];
        $this->assertEquals((object) $event_update, $response_body);

        // delete event
        $response = $this->jsonRequest('DELETE', 'events/' . $event['id'], TEST_USER);
        $this->assertEquals(200, $response->getStatusCode(), 'delete event');
        $this->assertEmpty((string) $response->getBody(), 'delete event - empty response body');
    }
    

    public function testGetEvents()
    {
        $events_dates = [['2022-01-30', '2022-02-03'],
                         ['2022-02-04', '2022-02-06']
                        ];

        $this->deleteEventsBefore('2023-01-01');
        $events_created = $this->createEvents($events_dates, TEST_USER);

        $response = $this->jsonRequest('GET', 'events', TEST_USER, 
                                      ['query' => ['start' => '2022-01-01', 'end' => '2022-12-31']]);
        $events_got = json_decode($response->getBody());
        $this->assertEquals($events_created, $events_got);  
    }

    public function testCreateEvent()
    {
        $this->deleteEventsBefore('2023-01-01');

        // incomplete event parameters
        $event = ['start' => '2022-01-30'];
        $response = $this->jsonRequest('POST', 'events', TEST_USER,
                                       ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('api-030', $response_body->code);

        // event title != username
        $event = ['title' => 'test', 
                  'start' => '2022-01-03', 
                  'end' => '2022-01-04'];
        $response = $this->jsonRequest('POST', 'events', TEST_USER,
                                       ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('api-020', $response_body->code);

        // as admin + event title != username
        $event = ['title' => TEST_USER, 
                  'start' => '2022-01-03', 
                  'end' => '2022-01-04'];
        $response = $this->jsonRequest('POST', 'events', 'admin',
                                       ['json' => $event]);
        $response_body = json_decode($response->getBody());
        $this->assertEquals(201, $response->getStatusCode());
    
        // create event
        $event = ['title' => TEST_USER, 
                  'start' => '2022-02-03', 
                  'end' => '2022-02-04'];
        $response = $this->jsonRequest('POST', 'events', TEST_USER,
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
        $this->createEvents($events_dates, TEST_USER);

        // overlap_start
        $this->createEventOverlap(['2022-02-01', '2022-02-05'], 
                                  TEST_USER,
                                  'overlap_start');

        // overlap_end
        $this->createEventOverlap(['2022-02-04', '2022-02-06'],
                                  TEST_USER,
                                  'overlap_end');

        // overlap_both
        $this->createEventOverlap(['2022-02-01', '2022-02-06'],
                                  TEST_USER,
                                  'overlap_both');
    }

}
