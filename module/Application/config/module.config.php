<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

return array(

    'controllers' => array(
        'invokables' => array(
            'Application\Controller\Application' => 'Application\Controller\ApplicationController',
            'Application\Controller\Index' => 'Application\Controller\IndexController',
        ),
    ),

    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Index',
                        'action' => 'index',
                    ),
                ),
            ),
            'callback' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route' => '/callback',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Index',
                        'action' => 'callback',
                    ),
                ),
            ),
        ),
    ),


    // Console routes
    'console' => array(
        'router' => array(
            'routes' => array(
                'sync' => array(
                    'options' => array(
                        'route' => 'sync <access_token> <refresh_token>',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Application',
                            'action'     => 'sync',
                        ),
                    ),
                ),
            ),
        ),
    ),
);
