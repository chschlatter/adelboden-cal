<?php

class UserMapper {

    protected $db;
    // protected $lock_fp;

    public function __construct($db)
    {
        $this->db = $db;
        // $this->lock_fp = fopen($_ENV['LOCK_FILE'], 'c');
    }

    /*
    protected function getExclusiveLock()
    {
        flock($this->lock_fp, LOCK_EX);
    }

    protected function releaseExclusiveLock()
    {
        flock($this->lock_fp, LOCK_UN);
    }
    */

    public function authenticate($user, \Bullet\Response $response)
    {
        $query_str = 'SELECT * FROM users WHERE name = :name;';
        $query = $this->db->prepare($query_str);
        $query->bindValue(':name', $user['name']);
        $result = $query->execute();

        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($user['name'] == 'admin') {
                if ($user['password'] != $_ENV['APP_ADMIN_PWD']) {
                    $data = ['message' => 'Wrong password',
                             'code' => 'pwd-1'];
                    $response->status(400)->content($data);
                    return false;
                }
            }
            $response->status(200)->content(['message' => 'Login successfull']);
            return true;
        }
        $data = ['message' => 'User not found',
                 'code' => 'usr-1'];
        $response->status(404)->content($data);
    }
}