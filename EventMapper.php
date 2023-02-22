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

    public function getEvents($response, $range = null)
    {
        $query_str = 'SELECT id, title, start, end FROM events';
        if ($range) {
            $query_str .= ' WHERE start BETWEEN :start AND :end';
            $query = $this->db->prepare($query_str . ';');
            $query->bindValue(':start', $range['start']);
            $query->bindValue(':end', $range['end']);
            $result = $query->execute();
        } else {
            $result = $this->db->query($query_str . ';');
        }
        
        $events_array = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            array_push($events_array, $row);
        }

        $response->status(200);
        $response->content($events_array);
    }

    public function createEvent($event, $response)
    {
        $this->getExclusiveLock();
        try {
            if ($this->findOverlap($event, $response)) {
                $response->status(422);
                return;
            }

            $query_str = 
                'INSERT INTO events (title, start, end, end_inclusive) ' .
                'VALUES (:title, :start, :end, :end_inclusive);';
            $this->dbExecute($query_str, $event);
            $event['id'] = $this->db->lastInsertRowID();

            $response->status(201);
            $response->content($event);
        } finally {
            $this->releaseExclusiveLock();
        }       
    }

    public function updateEvent($event, $response)
    {
        $this->getExclusiveLock();
        try {
            if ($this->findOverlap($event, $response)) {
                $response->status(422);
                return;
            }

            $query_str = 
                'UPDATE events SET (title, start, end, end_inclusive) ' .
                '= (:title, :start, :end, :end_inclusive) ' .
                'WHERE id = :id;';
            Log::info(__METHOD__ . ': ' . $query_str, $event);
            $this->dbExecute($query_str, $event);

            $response->status(200);
            $response->content($event);

        } finally {
            $this->releaseExclusiveLock();
        }
    }

    public function findOverlap($event, $response)
    {
        $query = $this->db->prepare(
            'SELECT * FROM events WHERE ' .
            ':start < "end_inclusive" AND :end_inclusive > "start" AND :id != "id";');
        $query->bindValue(':start', $event['start']);
        $query->bindValue(':end_inclusive', $event['end_inclusive']);
        $query->bindValue(':id', $event['id']);
        $result = $query->execute();

         while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $overlap['overlap_found'] = true;
            if ($event['start'] > $row['start'] and 
                $event['start'] < $row['end_inclusive']) {
                $overlap['input_start_date'] = 'overlap found';
            }
            if ($event['end_inclusive'] > $row['start'] and
                $event['end_inclusive'] < $row['end_inclusive']) {
                $overlap['input_end_date'] = 'overlap found';
            }
            if (is_array($response->content())) {
                $response->content(array_merge($response->content(), $overlap));
            } else {
                $response->content($overlap);
            }
            
            return true;
        }
        
        return false;
    }

    public function deleteEvent($event_id, $response)
    {
        if (!$this->db->querySingle("SELECT * FROM events WHERE id = $event_id;")) {
            $response->status(404);
            $response->content(['message' => "Could not find event with id [$event_id]."]);
        }

        if ($this->db->exec("DELETE FROM events WHERE id = $event_id;")) {
            $response->status(200);
            $response->content(['message' => "Deleted event with id [$event_id]."]);
        } else {
            $response->status(500);
            $response->content(['message' => 'DB error: ' . $this->db->lastErrorMsg()]);
        }
    }
}