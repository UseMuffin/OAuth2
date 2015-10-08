<?php
namespace Muffin\OAuth2\Test\TestCase\Auth;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Network\Request;
use Cake\Network\Response;
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
                ]
            ],
        ],
    ];

    public $registry;

    public $oauth;

    public function setUp()
    {
        parent::setUp();

        $this->registry = $this->getMock('Cake\Controller\ComponentRegistry');
        $this->oauth = $this->getMockForOAuth();
    }

    public function getMockForOAuth($methods = [])
    {
        if (empty($methods)) {
            $methods = ['provider', '_findUser'];
        }

        $registry = $this->getMock('Cake\Controller\ComponentRegistry');
        return $this->getMock($this->class, $methods, [$registry, $this->config]);
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
                ['github' => [
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
                ]]
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
        $this->assertFalse($oauth->getUser(new Request('/', ['query' => ['code' => 'foo']])));
    }

    public function testGetUserMissingCodeInQuery()
    {
        $request = new Request(['url' => '/', 'params' => ['provider' => 'github']]);
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

        $this->oauth->config($this->oauth->config('providers.github'));
        $result = $this->invokeMethod('_map', [$data]);
        $expected = ['username' => 'foo', 'more' => 'stuff'];
        $this->assertEquals($expected, $result);
    }

    public function testProviderMissingFromRequest()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->config);
        $this->assertFalse($oauth->provider(new Request('/')));
    }

    public function testProvider()
    {
        $request = new Request(['url' => '/', 'params' => ['provider' => 'github']]);
            $oauth = $this->getMockForOAuth(['_getProvider']);

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
        $provider = $this->getMock('League\OAuth2\Client\Provider\Github', [], [], '', false);

        $this->oauth->expects($this->any())
            ->method('provider')
            ->will($this->returnValue($provider));

        $url = '/';
        $params = ['provider' => 'github'];
        $query = ['code' => 'bar', 'state' => 'foo'];
        $request = new Request(compact('url', 'params', 'query'));

        $result = $this->oauth->getUser($request);
        $this->assertFalse($result);

        $query += ['state' => 'foo'];
        $request = new Request(compact('url', 'params', 'query'));

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
        $this->oauth->config($this->oauth->normalizeConfig($this->config));
        $this->oauth->config($this->oauth->config('providers.github'), false);

        $token = $this->getMock('League\OAuth2\Client\Token\AccessToken', [], [], '', false);

        $owner = $this->getMock('League\OAuth2\Client\Provider\GenericResourceOwner', [], [], '', false);
        $owner->expects($this->once())
            ->method('toArray')
            ->will($this->returnValue(['login' => 'foo']));

        $provider = $this->getMock('League\OAuth2\Client\Provider\Github', [], [], '', false);
        $provider->expects($this->once())
            ->method('getAccessToken')
            ->with('authorization_code', ['code' => 'bar'])
            ->will($this->returnValue($token));
        $provider->expects($this->once())
            ->method('getResourceOwner')
            ->with($token)
            ->will($this->returnValue($owner));


        $session = $this->getMock('Cake\Network\Session');
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
        $request = new Request(compact('url', 'params', 'query', 'session'));

        $result = $this->oauth->getUser($request);
        $this->assertEquals(['username' => 'foo', 'token' => $token], $result);
    }

    public function testUnauthenticated()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->config);

        $request = new Request('/');
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertNull($result);

        $url = '/';

        $query = ['code' => 'bar'];
        $params = ['provider' => 'github'];
        $request = new Request(compact('url', 'params', 'query'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertNull($result);

        $query = ['code' => 'bar'];
        $request = new Request(compact('url', 'query'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertNull($result);

        $session = $this->getMock('Cake\Network\Session', ['write']);
        $session->expects($this->once())
            ->method('write')
            ->with('oauth2state', 'foobar');

        $expected = '/https:\/\/github\.com\/login\/oauth\/authorize\?/';
        $params = ['provider' => 'github'];
        $request = new Request(compact('url', 'params', 'session'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertInstanceOf('Cake\Network\Response', $result);
        $this->assertRegExp($expected, $result->location());

        $oauth->config('options.state', null);
        $expected = '/https:\/\/github\.com\/login\/oauth\/authorize\?/';
        $params = ['provider' => 'github'];
        $request = new Request(compact('url', 'params', 'session'));
        $response = new Response();
        $result = $oauth->unauthenticated($request, $response);
        $this->assertInstanceOf('Cake\Network\Response', $result);
        $this->assertRegExp($expected, $result->location());
    }
}
