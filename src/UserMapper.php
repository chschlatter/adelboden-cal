<?php

namespace CalApi;

use CalApi\SQLiteDB;
use CalApi\Event;
use CalApi\ApiException;

class UserMapper 
{
    const ADMIN = 0x1;
    const USER = 0x2;

    private array $users;
    protected SQLiteDB $db;
    // protected $lock_fp;

    public function __construct(SQLiteDB $db)
    {
        $this->db = $db;
        $this->users = $this->_getUsers();
    }

    private function _getUsers(): array
    {
        $query = 'SELECT * FROM users;';
        $db_result = $this->db->execute($query);

        while ($row = $db_result->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row['name'];
        }
        return $users;
    }

    public function get(): array
    {
        return $this->users;
    }

    public function add(array $user): void
    {
        if (in_array($user['name'], $this->users)) {
            throw new ApiException('user-010');
        }

        $query = 'INSERT INTO users (name) VALUES (:name);';
        $this->db->execute($query, $user);
    }

    public function delete(array $user): void
    {
        if (!in_array($user['name'], $this->users)) {
            throw new ApiException('user-011');
        }

        $query = 'DELETE FROM users WHERE name = :name;';
        $this->db->execute($query, $user);
    }

/*

    public function role(string $username, $roles)
    {
        if (($roles & self::ADMIN) && $username == 'admin') {
            return true;
        }
        if (($roles & self::USER) && in_array($username, $this->users)) {
            return true;
        }
        return false;
    }

    public function isAdmin(string $username)
    {
        return $username == 'admin';
    }

    public function isUser(string $username)
    {
        return in_array($username, $this->users);
    }

    public function hasWriteAccess(string $username, string $event_title): bool
    {
        if ($this->isAdmin($username)) {
            return true;
        }
        if ($this->isUser($username)) {
            return ($username == $event_title);
        }
        return false;
    }

    public function login(array $user): string
    {
        if ($user['name'] == 'admin') {
            if (isset($user['password']) && $user['password'] != $this->admin_pwd) {
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
        $username_base64 = base64_encode($username);
        $signature = hash('sha256', $username . $this->admin_pwd);
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
*/
}