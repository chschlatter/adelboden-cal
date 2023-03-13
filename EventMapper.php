<?php

class EventMapper {

    protected $db;
    protected $lock_fp;

    public function __construct($db)
    {
        $this->db = $db;
        $this->lock_fp = fopen($_ENV['LOCK_FILE'], 'c');
    }

    protected function getExclusiveLock()
    {
        flock($this->lock_fp, LOCK_EX);
    }

    protected function releaseExclusiveLock()
    {
        flock($this->lock_fp, LOCK_UN);
    }

    private function dbExecute($query_str, $event)
    {
       $query = $this->db->prepare($query_str);
       $query->bindValue(':title', $event['title']);
       $query->bindValue(':start', $event['start']);
       $query->bindValue(':end', $event['end']);
       $query->bindValue(':end_inclusive', $event['end_inclusive']);
       $query->bindValue(':id', $event['id']);
       $query->execute();       
    }


    public function getEvents($range = null): array
    {
        $query_str = 'SELECT id, title, start, end FROM events';
        if ($range['start'] and $range['end']) {
            $query_str .= ' WHERE start BETWEEN :start AND :end';
            $query = $this->db->prepare($query_str . ';');
            $query->bindValue(':start', $range['start']);
            $query->bindValue(':end', $range['end']);
            $db_result = $query->execute();
        } else {
            $db_result = $this->db->query($query_str . ';');
        }
        
        $events_array = [];
        while ($row = $db_result->fetchArray(SQLITE3_ASSOC)) {
            array_push($events_array, $row);
        }

        return $events_array;
    }

    // /throws ApiException
    public function getEventTitle(int $event_id): string
    {
        $row = $this->db->querySingle(
               "SELECT * FROM events WHERE id = $event_id;",
               true);
        if (!$row) {
            throw new ApiException('event-011');
        }
        return $row['title'];
    }

    // /throws ApiException
    public function createEvent(array $event): array
    {
        $this->getExclusiveLock();
        try {
            $this->findOverlap($event);

            $query_str = 
                'INSERT INTO events (title, start, end, end_inclusive) ' .
                'VALUES (:title, :start, :end, :end_inclusive);';
            $this->dbExecute($query_str, $event);
            $event['id'] = $this->db->lastInsertRowID();
            unset($event['end_inclusive']);

            return $event;
        } finally {
            $this->releaseExclusiveLock();
        }       
    }

    // /throws ApiException
    public function updateEvent(array $event): array
    {
        $this->getExclusiveLock();
        try {
            $this->findOverlap($event);
            
            $query_str = 
                'UPDATE events SET (title, start, end, end_inclusive) ' .
                '= (:title, :start, :end, :end_inclusive) ' .
                'WHERE id = :id;';
            $this->dbExecute($query_str, $event);
            unset($event['end_inclusive']);

            return $event;
        } finally {
            $this->releaseExclusiveLock();
        }
    }

    // /throws ApiException
    public function findOverlap($event): void
    {
        $query = $this->db->prepare(
            'SELECT * FROM events WHERE ' .
            ':start < "end_inclusive" AND :end_inclusive > "start" AND :id != "id";');
        $query->bindValue(':start', $event['start']);
        $query->bindValue(':end_inclusive', $event['end_inclusive']);
        $query->bindValue(':id', $event['id']);
        $db_result = $query->execute();

        $overlap = false;
        while ($row = $db_result->fetchArray(SQLITE3_ASSOC)) {
            $overlap = true;
            if ($event['start'] > $row['start']) {
                $result['overlap_start'] = true;
            };
            if ($event['end_inclusive'] <= $row['end_inclusive']) {
                $result['overlap_end'] = true;
            };
        };

        if ($overlap) {
            throw new ApiException('event-010', $result);
        }
    }

    // /throws ApiException
    public function deleteEvent(int $event_id): void
    {
        if (!$this->db->querySingle("SELECT * FROM events WHERE id = $event_id;")) {
            throw new ApiException('event-011');
        }

        if (!$this->db->exec("DELETE FROM events WHERE id = $event_id;")) {
            throw new ApiException('api-010', ['db_error_msg' => $this->db->lastErrorMsg()]);
        }
    }

    // /throws ApiException
    public function deleteEvents($delete_params): void
    {
        $before = $delete_params['before'];
        if (!$this->db->exec("DELETE FROM events WHERE end < '$before';")) {
            throw new ApiException('api-010', ['db_error_msg' => $this->db->lastErrorMsg()]);
        }
    }

}