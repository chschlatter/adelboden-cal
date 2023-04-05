<?php

declare(strict_types=1);

namespace CalApi;

use SQLite3;
use SQLite3Result;
use CalApi\ApiException;

use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

class SQLiteDB
{
    public readonly SQLite3 $db;

    #[CodeCoverageIgnore]
    public function __construct(string $db_file, ?int $busy_timeout = null)
    {
        $this->db = new SQLite3($db_file);
        if ($busy_timeout !== null) {
            $this->db->busyTimeout($busy_timeout);
        }
    }

    public function execute(string $query, ?array $params = null): SQLite3Result
    {
        $stmt = $this->db->prepare($query);
        if ($params !== null) {
            foreach ($params as $name => $value) {
                $stmt->bindValue(":$name", $value);
            }
        }
        if (($result = $stmt->execute()) === false) {
            throw new ApiException('api-010', ['db_error_msg' => $this->db->lastErrorMsg()]);
        }
        return $result;
    }
}