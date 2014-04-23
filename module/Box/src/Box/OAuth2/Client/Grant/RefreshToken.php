<?php

namespace Box\OAuth2\Client\Grant;

use League\OAuth2\Client\Token\AccessToken as AccessToken;
use League\OAuth2\Client\Grant\GrantInterface;

class RefreshToken implements GrantInterface
{
    public function __toString()
    {
        return 'refresh_token';
    }

    public function prepRequestParams($defaultParams, $params)
    {
        if ( ! isset($params['refresh_token']) || empty($params['refresh_token'])) {
            throw new \BadMethodCallException('Missing refresh_token');
        }

        $params = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $params['refresh_token']
        );

        return array_merge($defaultParams, $params);
    }

    public function handleResponse($response = array())
    {
        return new AccessToken($response);
    }
}
