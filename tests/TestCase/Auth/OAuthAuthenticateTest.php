<?php

namespace Muffin\OAuth2\Test\TestCase\Auth;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Muffin\OAuth2\Auth\OAuthAuthenticate;

class OAuthAuthenticateTest extends TestCase
{
    public $class = 'Muffin\OAuth2\Auth\OAuthAuthenticate';

    public $config = [
        'providers' => [
            'github' => [
                'className' => 'League\OAuth2\Client\Provider\Github',
                'options' => [
                    'clientId' => 'foo',
                    'clientSecret' => 'bar',
                    'state' => 'foobar',
                ],
                'mapFields' => [
                    'username' => 'login',
                ],
            ],
        ],
    ];

    public $registry;

    public $oauth;

    public function setUp()
    {
        parent::setUp();

        $this->registry = $this->getMockBuilder('Cake\Controller\ComponentRegistry')->getMock();
        $this->oauth = $this->createMockForOAuth();
    }

    public function createMockForOAuth($methods = [])
    {
        if (empty($methods)) {
            $methods = ['provider', '_findUser'];
        }

        $registry = $this->getMockBuilder('Cake\Controller\ComponentRegistry')->getMock();

        return $this->getMockBuilder($this->class)
            ->setMethods($methods)
            ->setConstructorArgs([$registry, $this->config])
            ->getMock();
    }

    public function invokeMethod($name, $params = null)
    {
        $class = new \ReflectionClass($this->class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($this->oauth, $params);
    }

    public function testNormalizeConfigPassedByConfigure()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testNormalizeConfigThrowsException()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function provideNormalizeConfig()
    {
        $config = [
            'options' => ['state' => mt_rand(0, 99999999999)],
            'providers' => [
                'github' => [
                    'className' => 'League\OAuth2\Client\Provider\Github',
                    'options' => [
                        'clientId' => 'foo',
                        'clientSecret' => 'bar',
                    ],
                ],
            ],
        ];

        return [
            [
                $config,
                [
                    'github' => [
                        'className' => 'League\OAuth2\Client\Provider\Github',
                        'options' => [
                            'clientId' => 'foo',
                            'clientSecret' => 'bar',
                            'state' => $config['options']['state'],
                        ],
                        'collaborators' => [],
                        'fields' => [
                            'username' => 'username',
                            'password' => 'password',
                        ],
                        'userModel' => 'Users',
                        'scope' => [],
                        'contain' => null,
                        'passwordHasher' => 'Default',
                        'finder' => 'all',
                        'mapFields' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideNormalizeConfig
     */
    public function testNormalizeConfig($config, $expected)
    {
        $result = $this->oauth->normalizeConfig($config);
        $this->assertEquals($expected, $result['providers']);
    }

    public function testValidateConfigThrowsExceptionOnMissingProvider()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testValidateConfigThrowsExceptionOnInvalidProviderSettings()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testGetUserMissingProviderInRequest()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->config);
        $this->assertFalse($oauth->getUser(new ServerRequest(['url' => '/', 'query' => ['code' => 'foo']])));
    }

    public function testGetUserMissingCodeInQuery()
    {
        $request = new ServerRequest(['url' => '/', 'params' => ['provider' => 'github']]);
        $result = $this->oauth->getUser($request);
        $this->assertFalse($result);
    }

    public function testGetUserWithDisabledUserModel()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testGetUserForRegisteredUser()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testGetUserForUnregisteredUser()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testGetUserForInvalidUser()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testMap()
    {
        $data = ['login' => 'foo', 'more' => 'stuff'];
        $result = $this->invokeMethod('_map', [$data]);

        $this->assertEquals($data, $result);

        $this->oauth->setConfig($this->oauth->getConfig('providers.github'));
        $result = $this->invokeMethod('_map', [$data]);
        $expected = ['username' => 'foo', 'more' => 'stuff'];
        $this->assertEquals($expected, $result);
    }

    public function testProviderMissingFromRequest()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->config);
        $this->assertFalse($oauth->provider(new ServerRequest('/')));
    }

    public function testProvider()
    {
        $request = new ServerRequest(['url' => '/', 'params' => ['provider' => 'github']]);
        $oauth = $this->createMockForOAuth(['_getProvider']);

        $oauth->expects($this->once())
            ->method('_getProvider')
            ->with('github')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $oauth->provider($request));
    }

    public function testGetProvider()
    {
        $result = $this->invokeMethod('_getProvider', ['foo']);
        $this->assertFalse($result);

        $result = $this->invokeMethod('_getProvider', ['github']);
        $expected = $this->config['providers']['github']['className'];
        $this->assertInstanceOf($expected, $result);

        $github = new $expected();
        $this->config['providers']['github'] = $github;
        $result = $this->invokeMethod('_getProvider', ['github']);
        $this->assertInstanceOf($expected, $result);
    }

    public function testNormalizeConfig1()
    {
        $config = [
            'options' => ['state' => mt_rand(0, 99999999999)],
            'providers' => [
                'github' => [
                    'className' => 'League\OAuth2\Client\Provider\Github',
                    'options' => [
                        'clientId' => 'foo',
                        'clientSecret' => 'bar',
                    ],
                ],
            ],
        ];

        $result = $this->oauth->normalizeConfig($config);
        $expected = [
            'className' => 'League\OAuth2\Client\Provider\Github',
            'options' => [
                'clientId' => 'foo',
                'clientSecret' => 'bar',
                'state' => $config['options']['state'],
            ],
            'collaborators' => [],
            'fields' => [
                'username' => 'username',
                'password' => 'password',
            ],
            'userModel' => 'Users',
            'scope' => [],
            'contain' => null,
            'passwordHasher' => 'Default',
            'finder' => 'all',
            'mapFields' => [],
        ];
        $this->assertEquals($expected, $result['providers']['github']);
    }

    public function testGetUserWithMissingOrAlteredQueryState()
    {
        $this->oauth->setConfig($this->oauth->normalizeConfig($this->config));
        $this->oauth->setConfig($this->oauth->getConfig('providers.github'), false);

        $provider = $this->getMockBuilder('League\OAuth2\Client\Provider\Github')
            ->disableOriginalConstructor()
            ->getMock();

        $this->oauth->expects($this->any())
            ->method('provider')
            ->will($this->returnValue($provider));

        $url = '/';
        $params = ['provider' => 'github'];
        $query = ['code' => 'bar', 'state' => 'foo'];
        $request = new ServerRequest(compact('url', 'params', 'query'));

        $result = $this->oauth->getUser($request);
        $this->assertFalse($result);

        $query += ['state' => 'foo'];
        $request = new ServerRequest(compact('url', 'params', 'query'));

        $result = $this->oauth->getUser($request);
        $this->assertFalse($result);
    }

    public function newUser(Event $event, $provider, array $data)
    {
        $this->assertTrue(true);

        return $data;
    }

    public function testGetUser()
    {
        EventManager::instance()->on('Muffin/OAuth2.newUser', [$this, 'newUser']);
        $this->oauth->setConfig($this->oauth->normalizeConfig($this->config));
        $this->oauth->setConfig($this->oauth->getConfig('providers.github'), false);

        $token = $this->getMockBuilder('League\OAuth2\Client\Token\AccessToken')
            ->disableOriginalConstructor()
            ->getMock();

        $owner = $this->getMockBuilder('League\OAuth2\Client\Provider\GenericResourceOwner')
            ->disableOriginalConstructor()
            ->getMock();
        $owner->expects($this->once())
            ->method('toArray')
            ->will($this->returnValue(['login' => 'foo']));

        $provider = $this->getMockBuilder('League\OAuth2\Client\Provider\Github')
            ->disableOriginalConstructor()
            ->getMock();
        $provider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => 'bar'])
            ->will($this->returnValue($token));
        $provider->expects($this->once())
            ->method('getResourceOwner')
            ->with($token)
            ->will($this->returnValue($owner));

        $session = $this->getMockBuilder('Cake\Http\Session')->getMock();
        $session->expects($this->once())
            ->method('read')
            ->with('oauth2state')
            ->will($this->returnValue('foobar'));

        $this->oauth->expects($this->exactly(2))
            ->method('provider')
            ->will($this->returnValue($provider));

        $this->oauth->expects($this->once())
            ->method('_findUser')
            ->will($this->returnValue([]));

        $url = '/';
        $params = ['provider' => 'github'];
        $query = ['code' => 'bar', 'state' => 'foobar'];
        $request = new ServerRequest(compact('url', 'params', 'query', 'session'));

        $result = $this->oauth->getUser($request);
        $this->assertEquals(['username' => 'foo', 'token' => $token], $result);
    }

    public function testUnauthenticated()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->config);

        $request = new ServerRequest('/');
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertNull($result);

        $url = '/';

        $query = ['code' => 'bar'];
        $params = ['provider' => 'github'];
        $request = new ServerRequest(compact('url', 'params', 'query'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertNull($result);

        $query = ['code' => 'bar'];
        $request = new ServerRequest(compact('url', 'query'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertNull($result);

        $session = $this->getMockBuilder('Cake\Http\Session')
            ->setMethods(['write'])
            ->getMock();
        $session->expects($this->once())
            ->method('write')
            ->with('oauth2state', 'foobar');

        $expected = '/https:\/\/github\.com\/login\/oauth\/authorize\?/';
        $params = ['provider' => 'github'];
        $request = new ServerRequest(compact('url', 'params', 'session'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertInstanceOf('Cake\Http\Response', $result);
        $this->assertRegExp($expected, $result->getHeaderLine('Location'));

        $oauth->setConfig('options.state', null);
        $expected = '/https:\/\/github\.com\/login\/oauth\/authorize\?/';
        $params = ['provider' => 'github'];
        $request = new ServerRequest(compact('url', 'params', 'session'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertInstanceOf('Cake\Http\Response', $result);
        $this->assertRegExp($expected, $result->getHeaderLine('Location'));
    }
}
