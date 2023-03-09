<?php

class ApiResponse extends \Bullet\Response
{
    protected $_auth_info = array('auth_success' => false);

    public function error(string $error_code, array $properties = [])
    {
        $api_errors = array(
            'api-010'   => ['message' => 'Database error',
                            'status' => 500],
            'api-020'   => ['message' => 'Not authorized',
                            'status' => 401],

            'event-010' => ['message' => 'Overlap found',
                            'status'  => 422 ],
            'event-011' => ['message' => 'Could not find event in database',
                            'status'  => 404]
        );

        if (isset($api_errors[$error_code])) {
            $response = array('message' => $api_errors[$error_code]['message'],
                              'code' => $error_code);
            $this->content(json_encode(array_merge($response, $properties)));
            $this->status($api_errors[$error_code]['status']);
        } else {
            Log::warning(__METHOD__ . ': unknown $error_code [' . $error_code . '].',
                         $properties);
            $this->status(500)->content(json_encode(['code' => 'api-999', 'message' => 'General error']));
        }
        return $this;
    }

    public static function cookieAuth()
    {
        $result = array('auth_success' => false, 'username' => '');

        if (isset($_COOKIE['token'])) {
            try {
                [$username_b64, $sig_token] = explode('.', $_COOKIE['token']);
                $username = base64_decode($username_b64);
                $sig_server = hash('sha256', $username . $_ENV['APP_ADMIN_PWD']);
                if ($sig_token === $sig_server) {
                    $result['auth_success'] = true;
                    $result['username'] = $username;
                }
            } catch (Exception | Error $e) {
                Log::error('Exception|Error in cookie_auth(): ' . $e->getMessage());
            }
        }

        return $result;
    }

    public function auth()
    {
        $this->_auth_info = self::cookieAuth();
        return $this->_auth_info['auth_success'];
    }

    public function getAuthUsername()
    {
        return $this->_auth_info['username'] ?? '';
    }
}