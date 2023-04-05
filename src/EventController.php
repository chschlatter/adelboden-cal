<?php

namespace CalApi;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use CalApi\Event;
use CalApi\EventMapper;
use CalApi\IAM;

class EventController
{
    private EventMapper $events;
    private IAM $iam;

    public function __construct(EventMapper $events, IAM $iam)
    {
        $this->events = $events;
        $this->iam = $iam;
    }

    public function getEvents(Request  $request, 
                              Response $response): Response
    {
        $range = $request->getQueryParams();
        $event_list = $this->events->get($range);
        $response->getBody()->write(json_encode($event_list));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    }

    public function createEvent(Request  $request, 
                                Response $response, 
                                string   $username): Response
    {
        $event = new Event((string) $request->getBody());

        if (!$this->iam->hasWriteAccess($username, $event)) {
            throw new ApiException('api-020');
        }

        $this->events->create($event);
        $response->getBody()->write($event->toJSON());
        return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(201);
    }

    public function updateEvent(Request  $request, 
                                Response $response, 
                                string   $username, 
                                int      $id): Response
    {
        $event = new Event((string) $request->getBody());
        $event->id($id);

        $event_list = $this->events->get(['id' => (string) $id]);
        if (count($event_list) == 0) {
            throw new ApiException('event-011');
        }
        $existing_event = new Event($event_list[0]);
        
        if (!$this->iam->hasWriteAccess($username, $existing_event)) {
            throw new ApiException('api-020');
        }

        $this->events->update($event);
        $response->getBody()->write($event->toJSON());
        return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(200);
    }

    public function deleteEvent(Request  $request,
                                Response $response,
                                string   $username,
                                int      $id): Response
    {
        $event_list = $this->events->get(['id' => (string) $id]);
        if (count($event_list) == 0) {
            throw new ApiException('event-011');
        }
        $existing_event = new Event($event_list[0]);
        if (!$this->iam->hasWriteAccess($username, $existing_event)) {
            throw new ApiException('api-020');
        };
        $this->events->delete(['id' => $id]);
        return $response->withStatus(200);
    }

    public function deleteEvents(Request $request,
                                 Response $response): Response
    {
        $params = $request->getQueryParams();
        $this->events->delete($params);
        return $response->withStatus(200);
    }
}