<?php
namespace Muffin\OAuth2\Test\TestCase\Auth;

use Cake\Network\Request;
use Cake\Network\Response;
use Cake\TestSuite\TestCase;
use Muffin\OAuth2\Auth\OAuthAuthenticate;

class OAuthAuthenticateTest extends TestCase
{
    public $defaultConfig = [
        'providers' => [
            'github' => [
                'className' => 'League\OAuth2\Client\Provider\Github',
                'options' => [
                    'clientId' => 'foo',
                    'clientSecret' => 'bar',
                    'state' => 'foobar',
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

        $this->oauth = $this->getMock(
            'Muffin\OAuth2\Auth\OAuthAuthenticate',
            ['provider', '_findUser'],
            [$this->registry, $this->defaultConfig]
        );
    }

    public function testNormalizeConfig()
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
            'autoRegister' => false,
            'fields' => [
                'username' => 'username',
                'password' => 'password',
            ],
            'userModel' => 'Users',
            'scope' => [],
            'contain' => null,
            'passwordHasher' => 'Default',
        ];
        $this->assertEquals($expected, $result['providers']['github']);
    }

    public function testProvider()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->defaultConfig);
        $this->assertFalse($oauth->provider(new Request('/')));

        $request = new Request(['url' => '/', 'params' => ['provider' => 'github']]);
        $this->assertInstanceOf('League\OAuth2\Client\Provider\Github', $oauth->provider($request));
    }

    public function testGetUserWithNoProvider()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->defaultConfig);
        $this->assertFalse($oauth->getUser(new Request('/')));
    }

    public function testGetUserWithMissingQueryCode()
    {
        $provider = $this->getMock('League\OAuth2\Client\Provider\Github', [], [], '', false);

        $this->oauth->expects($this->once())
            ->method('provider')
            ->will($this->returnValue($provider));

        $request = new Request(['url' => '/', 'params' => ['provider' => 'github']]);

        $result = $this->oauth->getUser($request);
        $this->assertFalse($result);
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

    public function testGetUserWithRequiredStateAndAutoRegisterFalse()
    {
        $this->oauth->config($this->oauth->normalizeConfig($this->defaultConfig));
        $this->oauth->config($this->oauth->config('providers.github'), false);

        $token = $this->getMock('League\OAuth2\Client\Token\AccessToken', [], [], '', false);

        $owner = $this->getMock('League\OAuth2\Client\Provider\GenericResourceOwner', [], [], '', false);
        $owner->expects($this->once())
            ->method('toArray')
            ->will($this->returnValue(['username' => 'foo']));

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

        $this->oauth->expects($this->once())
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
        $this->assertFalse($result);
    }

    public function testGetUserWithAutoRegister()
    {
        $this->markTestSkipped('Not implemented yet.');
    }

    public function testGetUserWithAutoRegisterFalse()
    {
        $this->markTestSkipped('Not implemented yet.');
    }

    public function testGetUserWithAutoRegisterButNoListenerAttached()
    {
        $this->markTestSkipped('Not implemented yet.');
    }

    public function testUnauthenticated()
    {
        $oauth = new OAuthAuthenticate($this->registry, $this->defaultConfig);

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
