<?php

namespace Application\Controller;

use Box\OAuth2\Client\Provider\Box;
use Zend\Mvc\Controller\AbstractActionController;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $config = $this->getServiceLocator()->get('Config');

        $expectedUri = 'http://'
            . $_SERVER['SERVER_NAME']
            . ':'
            . $_SERVER['SERVER_PORT']
            . '/callback';

        if ($expectedUri != $config['oauth2']['redirectUri']) {
//            die('OAuth2 redirect route ' . $config['oauth2']['redirectUri'] .
//               ' not valid for ' . $expectedUri);
        }

        $provider = new Box($config['oauth2']);
        $provider->authorize(array('state' => md5(rand())));
    }

    public function callbackAction()
    {
        try {
            $config = $this->getServiceLocator()->get('Config');
            $provider = new Box($config['oauth2']);
            $token = $provider->getAccessToken('authorization_code', array('code' => $_GET['code']));
	    $user = $provider->getUserDetails($token);
        } catch (\Exception $e) {
            die('Unhandled Exception: ' . $e->getMessage());
        }

        die("php public/index.php sync " . $token->accessToken . " " . $token->refreshToken);
    }
}
