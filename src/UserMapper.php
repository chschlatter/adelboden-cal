<?php

namespace CalApi;

use CalApi\SQLiteDB;
use CalApi\Event;
use CalApi\ApiException;

use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

class UserMapper 
{
    const ADMIN = 0x1;
    const USER = 0x2;

    private array $users;
    protected SQLiteDB $db;
    // protected $lock_fp;

    #[CodeCoverageIgnore]
    public function __construct(SQLiteDB $db)
    {
        $this->db = $db;
        $this->users = $this->_getUsers();
    }

    #[CodeCoverageIgnore]
    private function _getUsers(): array
    {
        $query = 'SELECT * FROM users;';
        $db_result = $this->db->execute($query);

        $users = array();
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
}