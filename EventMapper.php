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

    public function getEvents($range = null)
    {
        $result = array('success'    => false,
                        'error_code' => '',
                        'properties' => []);

        $query_str = 'SELECT id, title, start, end FROM events';
        if ($range) {
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

        $result['success'] = true;
        $result['properties'] = $events_array;
        return $result;
    }

    public function createEvent(array $event)
    {
        $result = array('success' => false, 
                        'error_code' => '',
                        'properties' => []);

        $this->getExclusiveLock();
        try {
            $overlap_result = $this->findOverlap($event);
            if ($overlap_result['overlap_found']) {
                $result['error_code'] = 'event-010';
                $result['properties'] = 
                    ['overlap_start' => $overlap_result['overlap_start'],
                     'overlap_end' => $overlap_result['overlap_end']];
                return $result;
            };

            $query_str = 
                'INSERT INTO events (title, start, end, end_inclusive) ' .
                'VALUES (:title, :start, :end, :end_inclusive);';
            $this->dbExecute($query_str, $event);
            $event['id'] = $this->db->lastInsertRowID();

            $result['success'] = true;
            $result['properties'] = $event;
            return $result;
        } finally {
            $this->releaseExclusiveLock();
        }       
    }

    public function updateEvent(array $event)
    {
        $result = array('success' => false, 
                        'error_code' => '',
                        'properties' => []);

        $this->getExclusiveLock();
        try {
            $overlap_result = $this->findOverlap($event);
            if ($overlap_result['overlap_found']) {
                $result['error_code'] = 'event-010';
                $result['properties'] = 
                    ['overlap_start' => $overlap_result['overlap_start'],
                     'overlap_end' => $overlap_result['overlap_end']];
                return $result;
            };

            $query_str = 
                'UPDATE events SET (title, start, end, end_inclusive) ' .
                '= (:title, :start, :end, :end_inclusive) ' .
                'WHERE id = :id;';
            $this->dbExecute($query_str, $event);

            $result['success'] = true;
            $result['properties'] = $event;
            return $result;

        } finally {
            $this->releaseExclusiveLock();
        }
    }

    public function findOverlap($event)
    {
        $result = array('overlap_found' => false, 
                        'overlap_start' => false, 
                        'overlap_end'   => false);

        $query = $this->db->prepare(
            'SELECT * FROM events WHERE ' .
            ':start < "end_inclusive" AND :end_inclusive > "start" AND :id != "id";');
        $query->bindValue(':start', $event['start']);
        $query->bindValue(':end_inclusive', $event['end_inclusive']);
        $query->bindValue(':id', $event['id']);
        $db_result = $query->execute();

         while ($row = $db_result->fetchArray(SQLITE3_ASSOC)) {
            $result['overlap_found'] = true;
            if ($event['start'] > $row['start']) {
                $result['overlap_start'] = true;
            };
            if ($event['end_inclusive'] <= $row['end_inclusive']) {
                $result['overlap_end'] = true;
            };
        };

        return $result;
    }

    public function deleteEvent($event_id)
    {
        $result = array('success' => false, 
                        'error_code' => '',
                        'properties' => ['event_id' => $event_id]);

        if (!$this->db->querySingle("SELECT * FROM events WHERE id = $event_id;")) {
            $result['error_code'] = 'event-011';
            return $result;
        }

        if ($this->db->exec("DELETE FROM events WHERE id = $event_id;")) {
            $result['success'] = true;
        } else {
            $result['error_code'] = 'api-010';
            $result['properties'] = ['db_error_msg' => $this->db->lastErrorMsg()];
        }
        return $result;
    }
}