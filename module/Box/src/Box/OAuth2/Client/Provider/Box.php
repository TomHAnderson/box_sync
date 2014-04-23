<?php

namespace Box\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\User;
use League\OAuth2\Client\Provider\IdentityProvider;

class Box extends IdentityProvider
{
    public $responseType = 'json';

    public function urlAuthorize()
    {
        return 'https://www.box.com/api/oauth2/authorize';
    }

    public function urlAccessToken()
    {
        return 'https://www.box.com/api/oauth2/token';
    }

    public function urlUserDetails(\League\OAuth2\Client\Token\AccessToken $token)
    {
    return 'https://www.box.com/users/me';
        // return 'https://api.github.com/user?access_token='.$token;
    }

    public function userDetails($response, \League\OAuth2\Client\Token\AccessToken $token)
    {
        $user = new User;
        $user->uid = $response->id;
        $user->nickname = $response->login;
        $user->name = isset($response->name) ? $response->name : null;
        $user->email = isset($response->email) ? $response->email : null;
        $user->urls = array(
            'GitHub' => 'http://github.com/'.$user->login,
            'Blog' => $user->blog,
        );

        return $user;
    }

    public function userUid($response, \League\OAuth2\Client\Token\AccessToken $token)
    {
        return $response->id;
    }

    public function userEmail($response, \League\OAuth2\Client\Token\AccessToken $token)
    {
        return isset($response->email) && $response->email ? $response->email : null;
    }

    public function userScreenName($response, \League\OAuth2\Client\Token\AccessToken $token)
    {
        return $response->name;
    }
}
