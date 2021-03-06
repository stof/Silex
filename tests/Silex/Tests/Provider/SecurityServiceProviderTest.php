<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silex\Tests\Provider;

use Silex\Application;
use Silex\WebTestCase;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * SecurityServiceProvider
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityServiceProviderTest extends WebTestCase
{
    public function setUp()
    {
        if (!is_dir(__DIR__.'/../../../../vendor/symfony/security')) {
            $this->markTestSkipped('Security dependency was not installed.');
        }

        parent::setUp();
    }

    public function test()
    {
        $app = $this->app;

        $client = $this->createClient();

        $client->request('get', '/');
        $this->assertEquals('ANONYMOUS', $client->getResponse()->getContent());

        $client->request('post', '/login_check', array('_username' => 'fabien', '_password' => 'bar'));
        $this->assertEquals('Bad credentials', $app['security.last_error']($client->getRequest()));
        // hack to re-close the session as the previous assertions re-opens it
        $client->getRequest()->getSession()->save();

        $client->request('post', '/login_check', array('_username' => 'fabien', '_password' => 'foo'));
        $this->assertEquals('', $app['security.last_error']($client->getRequest()));
        $client->getRequest()->getSession()->save();
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertEquals('http://localhost/', $client->getResponse()->headers->get('Location'));

        $client->request('get', '/');
        $this->assertEquals('fabienAUTHENTICATED', $client->getResponse()->getContent());
        $client->request('get', '/admin');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());

        $client->request('get', '/logout');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertEquals('http://localhost/', $client->getResponse()->headers->get('Location'));

        $client->request('get', '/');
        $this->assertEquals('ANONYMOUS', $client->getResponse()->getContent());

        $client->request('get', '/admin');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertEquals('http://localhost/login', $client->getResponse()->headers->get('Location'));

        $client->request('post', '/login_check', array('_username' => 'admin', '_password' => 'foo'));
        $this->assertEquals('', $app['security.last_error']($client->getRequest()));
        $client->getRequest()->getSession()->save();
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertEquals('http://localhost/admin', $client->getResponse()->headers->get('Location'));

        $client->request('get', '/');
        $this->assertEquals('adminAUTHENTICATEDADMIN', $client->getResponse()->getContent());
        $client->request('get', '/admin');
        $this->assertEquals('admin', $client->getResponse()->getContent());
    }

    public function createApplication()
    {
        $app = new Application();
        $app->register(new SessionServiceProvider());
        $app->register(new SecurityServiceProvider(), array(
            'security.firewalls' => array(
                'login' => array(
                    'pattern' => '^/login$',
                ),
                'default' => array(
                    'pattern' => '^.*$',
                    'anonymous' => true,
                    'form' => true,
                    'logout' => true,
                    'users' => array(
                        // password is foo
                        'fabien' => array('ROLE_USER', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
                        'admin'  => array('ROLE_ADMIN', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
                    ),
                ),
            ),
            'security.access_rules' => array(
                array('^/admin', 'ROLE_ADMIN'),
            ),
            'security.role_hierarchy' => array(
                'ROLE_ADMIN' => array('ROLE_USER'),
            ),
        ));

        $app->get('/login', function(Request $request) use ($app) {
            $app['session']->start();

            return $app['security.last_error']($request);
        });

        $app->get('/', function() use ($app) {
            $user = $app['security']->getToken()->getUser();

            $content = is_object($user) ? $user->getUsername() : 'ANONYMOUS';

            if ($app['security']->isGranted('IS_AUTHENTICATED_FULLY')) {
                $content .= 'AUTHENTICATED';
            }

            if ($app['security']->isGranted('ROLE_ADMIN')) {
                $content .= 'ADMIN';
            }

            return $content;
        });

        $app->get('/admin', function() use ($app) {
            return 'admin';
        });

        $app['session.test'] = true;

        return $app;
    }
}
