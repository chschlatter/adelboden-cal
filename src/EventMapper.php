<?php

namespace CalApi;

use DateTimeImmutable;
use SQLite3;
use SQLite3Result;
use CalApi\SQLiteDB;
use CalApi\Event;

class EventMapper {

    protected SQLiteDB $db;
    protected $lock_fp;

    public function __construct(SQLiteDB $db)
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

    public function get(?array $search_params = null, 
                        bool   $extended = false): array
    {
        $query = 'SELECT * FROM events';
        if ($search_params) {
            $where = array();
            if (isset($search_params['id'])) {
                $where[] = 'id = :id';
            };
            if (isset($search_params['start'])) {
                $where[] = 'end > :start';
            }
            if (isset($search_params['end'])) {
                $where[] = 'start < :end';
            }

            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $db_result = $this->db->execute($query . ';', $search_params);
        $events_array = [];
        while ($row = $db_result->fetchArray(SQLITE3_ASSOC)) {
            $row['id'] = (string) $row['id'];
            if (!$extended) {
                unset($row['end_inclusive']);
            }
            array_push($events_array, $row);
        }

        return $events_array;
    }

    public function create(Event $event): void
    {
        $this->getExclusiveLock();
        try {
            $this->overlaps($event);

            $query =                 
                'INSERT INTO events (title, start, end, end_inclusive) ' .
                'VALUES (:title, :start, :end, :end_inclusive);';
            $this->db->execute($query, $event->toArray(true));
            $event->id($this->db->db->lastInsertRowID());
        } finally {
            $this->releaseExclusiveLock();
        }       
    }

    public function update(Event $event): void
    {
        $this->getExclusiveLock();
        try {
            $this->overlaps($event);

            $query = 
                'UPDATE events SET (title, start, end, end_inclusive) ' .
                '= (:title, :start, :end, :end_inclusive) ' .
                'WHERE id = :id;';
            $this->db->execute($query, $event->toArray(true));
        } finally {
            $this->releaseExclusiveLock();
        }     
    }

    public function overlaps(Event $event): void
    {
        $query = 
            'SELECT * FROM events WHERE ' .
            ':start < "end_inclusive" AND :end_inclusive > "start" AND :id != "id";';
        $db_result = $this->db->execute($query, $event->toArray(true));

        $overlap = false;
        while ($row = $db_result->fetchArray(SQLITE3_ASSOC)) {
            $overlap = true;
            $result = array();
            $event_arr = $event->toArray(true);
            if ($event_arr['start'] > $row['start']) {
                $result['overlap_start'] = true;
            };
            if ($event_arr['end_inclusive'] <= $row['end_inclusive']) {
                $result['overlap_end'] = true;
            };
        };

        if ($overlap) {
            throw new ApiException('event-010', $result);
        }
    }

    public function delete(array $search_params): void
    {
        $query = 'DELETE FROM events';
        if (isset($search_params['id'])) {
            $query .= ' WHERE id = :id';
        }
        elseif (isset($search_params['before'])) {
            $query .= ' WHERE end < :before'; 
        }
        else {
            throw new \Exception('EventMapper::delete(): Wrong $search_params');
        }

        $this->db->execute($query . ';', $search_params);
    }

}