<?php

declare(strict_types=1);

namespace CalApi;

use DateTimeImmutable;
use SQLite3Stmt;
use JsonSerializable;

class Event implements JsonSerializable
{
    private string $id, $title;
    private DateTimeImmutable $start, $end, $end_inclusive;

    public function __construct(mixed $event)
    {
        if (is_string($event)) {
            $this->_fromJSON($event);
        } elseif (is_array($event)) {
            $this->_fromArray($event);
        }
    }

    private function _fromArray(array $event): void
    {
        if (!isset($event['title'], $event['start'], $event['end'])) {
            throw new \Exception('Event.fromArray(): title, start, and end ' .
                            'parameters are mandatory.');
        }
        $this->id = (string) ($event['id'] ?? 0);
        $this->title = $event['title'];
        $this->start = new DateTimeImmutable($event['start']);
        $this->end = new DateTimeImmutable($event['end']);
        $this->end_inclusive = $this->end->modify('-1 day');    
    }

    private function _fromJSON(string $event_json): void
    {
        $event_arr = json_decode($event_json, true);
        $this->_fromArray($event_arr);
    }

    public function __toString()
    {
        return $this->toJSON();
    }

    public function id(mixed $id = null): string
    {
        if ($id === null) {
            return $this->id;
        } else {
            $this->id = (string) $id;
            return $this->id;
        }
    }

    public function titleEquals($title): bool
    {
        return $this->title == $title;
    }

    public function toArray(bool $extended = false): array
    {
        $event_arr = [
            'id' => $this->id,
            'title' => $this->title,
            'start' => $this->start->format('Y-m-d'),
            'end' => $this->end->format('Y-m-d')
                ];
        if ($extended) {
            $event_arr['end_inclusive'] = $this->end_inclusive->format('Y-m-d');
        }
        return $event_arr;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJSON(bool $extended = false): string
    {
        return json_encode($this->toArray($extended));
    }

    public function bindValues(SQLite3Stmt $stmt): void
    {
        $stmt->bindValue(':id', $this->id);
        $stmt->bindValue(':title', $this->title);
        $stmt->bindValue(':start', $this->start->format('Y-m-d'));
        $stmt->bindValue(':end', $this->end->format('Y-m-d'));
        $stmt->bindValue(':end_inclusive', $this->end_inclusive->format('Y-m-d'));
    }

}