<?php

class ApiRequest extends \Bullet\Request
{
    private $_auth_info = [];
    protected $users;

    protected function validateDate($date, $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public function userMapper(UserMapper $users = null)
    {
        if ($users === null) {
            return $this->users;
        }
        
        $this->users = $users;
    }

    /**
     * /throws ApiException
     */
    public function validateEvent(): array
    {
        $event = $this->json();
        if (!isset($event['title'], $event['start'], $event['end'])) {
            throw new ApiException('api-030');
        }

        $end_date_exclusive = new DateTimeImmutable($event['end']);
        $event['end_inclusive'] = 
            $end_date_exclusive->modify('-1 day')->format('Y-m-d');

        return $event;
    }

    /**
     * /throws ApiException
     */
    public function validateUserJson(): array
    {
        $user = $this->json();
        if (!isset($user['name'])) {
            throw new ApiException('api-030');
        }
        if ($user['name'] == 'admin' and !isset($user['password'])) {
            throw new ApiException('api-030');
        }

        return $user;
    }

    /**
     * /throws ApiException
     */
    public function validateGetEvents(): array
    {
        $result['start'] = $this->get('start');
        $result['end'] = $this->get('end');

        if ($result['start'] xor $result['end']) {
            throw new ApiException('event-021');
        }

        return $result;
    }

    /**
     * /throws ApiException
     */
    public function validateEventsDelete(): array
    {
        $result['before'] = $this->get('before');
        if (!$result['before']) {
            throw new ApiException('api-031');
        }
        if (!$this->validateDate($result['before'])) {
            throw new ApiException('event-020');
        }
        return $result;
    }

    public static function createCookieToken(string $username): string
    {
        $username_base64 = base64_encode($username);
        $signature = hash('sha256', $username . $_ENV['APP_ADMIN_PWD']);
        return $username_base64 . '.' . $signature;
    }

    /**
     * /throws ApiException
     */
    public function loginAuth(array $user): void
    {
        if ($user['name'] == 'admin') {
            if ($user['password'] != $_ENV['APP_ADMIN_PWD']) {
                throw new ApiException('auth-010');
            }
        } else {
            $db_users = $this->users->getUsers();
            if (!in_array($user['name'], $db_users)) {
                throw new ApiException('auth-011');
            }
        }

        setcookie('token', 
                  self::createCookieToken($user['name']), 
                  array('path' => '/',
                        'expires' => 0, // strtotime('+300 days')
                        'httponly' => true,
                        'samesite' => 'strict'));
    }

    /**
     * /throws ApiException
     */
    public static function cookieAuth(array $valid_users): array
    {
        $result = array();
        if (isset($_COOKIE['token'])) {
            try {
                [$username_b64, $sig_token] = explode('.', $_COOKIE['token']);
                $username = base64_decode($username_b64);
                $sig_server = hash('sha256', $username . $_ENV['APP_ADMIN_PWD']);
            } catch (Exception | Error $e) {
                Log::error('Exception|Error in cookie_auth(): ' . $e->getMessage());
                throw new ApiException('api-020');
            }

            if ($sig_token === $sig_server) {
                if ($username == 'admin' or in_array($username, $valid_users)) {
                    $result['username'] = $username;
                    return $result;
                }
            }
        }
        throw new ApiException('api-020');
    }

    /**
     * /throws ApiException
     */
    public function auth(): void
    {
        $arg_list = func_get_args();

        $this->_auth_info = self::cookieAuth($this->users->getUsers());
        $auth_username = $this->getUsername();

        if ($arg_list && !in_array($auth_username, $arg_list)) {
            throw new ApiException('api-020');
        }
    }

    public function getUsername()
    {
        return $this->_auth_info['username'] ?? '';
    }
}