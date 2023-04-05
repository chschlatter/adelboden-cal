<?php

namespace CalApi;

use CalApi\Event;
use CalApi\UserMapper;
use CalApi\ApiException;

use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

class IAM
{
    const ADMIN = 0x1;
    const USER = 0x2;

    private array $users;
    private string $admin_pwd;

    #[CodeCoverageIgnore]
    public function __construct(UserMapper $user_mapper, 
                                string $admin_pwd)
    {
        $this->users = $user_mapper->get();
        $this->admin_pwd = $admin_pwd;
    }

    public function isAdmin(string $username)
    {
        return $username == 'admin';
    }

    public function isUser(string $username)
    {
        return in_array($username, $this->users);
    }

    public function hasWriteAccess(string $username, 
                                   Event $event): bool
    {
        if ($this->isAdmin($username)) {
            return true;
        }
        if ($this->isUser($username)) {
            return $event->titleEquals($username);
        }
        return false;
    }

    public function role(string $username, int $roles)
    {
        if (($roles & self::ADMIN) && $username == 'admin') {
            return true;
        }
        if (($roles & self::USER) && in_array($username, $this->users)) {
            return true;
        }
        return false;
    }

    public function login(array $user): string
    {
        if ($user['name'] == 'admin') {
            if (isset($user['password']) && 
                $user['password'] != $this->admin_pwd) {
                throw new ApiException('auth-010');
            }
        } else {
            if (!in_array($user['name'], $this->users)) {
                throw new ApiException('auth-011');
            }
        }

        return $this->createCookieToken($user['name']);
    }

    public function createCookieToken(string $username): string
    {
        return self::createCookieTokenPwd($username, $this->admin_pwd);
    }

    public static function createCookieTokenPwd(string $username,
                                                string $admin_pwd): string
    {
        $username_base64 = base64_encode($username);
        $signature = hash('sha256', $username . $admin_pwd);
        return $username_base64 . '.' . $signature;
    }

    public function verifyToken(array $cookies): string|bool
    {
        if (isset($cookies['token']) && 
            preg_match('/^([^.]+)\.([^.]+)$/', $cookies['token'], $matches)) {
            $username_b64 = $matches[1];
            $sig_token = $matches[2];
            $username = base64_decode($username_b64);
            $sig_server = hash('sha256', $username . $this->admin_pwd);

            if ($sig_token === $sig_server) {
                if (!$this->isAdmin($username) && !$this->isUser($username)) {
                    return false;
                }
                return $username;
            }
        }

        return false;
    }

    public function cookieAuth(): ?string
    {
        if (isset($_COOKIE['token'])) {
            try {
                [$username_b64, $sig_token] = explode('.', $_COOKIE['token']);
                $username = base64_decode($username_b64);
                $sig_server = hash('sha256', $username . $this->admin_pwd);
            } catch (\Exception | \Error $e) {
                Log::error('Exception|Error in cookieAuth(): ' . $e->getMessage());
                return null;
            }

            if ($sig_token === $sig_server) {
                if ($this->isAdmin($username) or $this->isUser($username)) {
                    return $username;
                }
            }
        }
        return null;
    }
}