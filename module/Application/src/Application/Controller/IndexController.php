<?php

namespace Application\Controller;

use Box\OAuth2\Client\Provider\Box;
use Zend\Mvc\Controller\AbstractActionController;
use Application\Service\Box as BoxService;

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

        $_SESSION['access_token'] = $token->accessToken;

        $this->plugin('redirect')->toRoute('sync');
    }

    public function syncAction()
    {
        $boxService = new BoxService();

        $boxService->syncDown(
            __DIR__ . '/../../../../../data/test1',
            '/test1'
        );

        die('syncDown complete');
    }
}
