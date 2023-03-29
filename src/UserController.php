<?php

namespace CalApi;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use HansOtt\PSR7Cookies\SetCookie;
use CalApi\Event;
use CalApi\EventMapper;
use CalApi\IAM;

class UserController
{
    private UserMapper $users;
    private IAM $iam;

    public function __construct(UserMapper $users, IAM $iam)
    {
        $this->users = $users;
        $this->iam = $iam;
    }

    public function getUsers(Request  $request, 
                             Response $response): Response
    {
        $users_list = $this->users->get();
        $response->getBody()->write(json_encode($users_list));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(200);
    }

    public function createUser(Request  $request, 
                               Response $response): Response
    {
        $user = json_decode($request->getBody(), true);
        $this->users->add($user);
        return $response->withStatus(201);
    }

    public function deleteUser(Request  $request, 
                               Response $response,
                               string   $name): Response
    {
        $user = ['name' => $name];
        $this->users->delete($user);
        return $response->withStatus(200);
    }

    public function login(Request  $request, 
                          Response $response): Response
    {
        $user = json_decode($request->getBody(), true);
        $cookie_token = $this->iam->login($user);

        // name, value, expires [eg strtotime('+300 days')],  
        // path, domain, secure, httpOnly, sameSite
        $cookie = new SetCookie('token', $cookie_token, 0, '/', '', false, true, 'strict');
        $response = $cookie->addToResponse($response);
        return $response->withStatus(200);
    }

}