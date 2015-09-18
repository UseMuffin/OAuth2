<?php
namespace Muffin\OAuth2\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Event\EventDispatcherTrait;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Utility\Hash;
use League\OAuth2\Client\Provider\AbstractProvider;
use RuntimeException;

class OAuthAuthenticate extends BaseAuthenticate
{

    use EventDispatcherTrait;

    protected $_provider;

    /**
     * Constructor
     *
     * @param \Cake\Controller\ComponentRegistry $registry The Component registry used on this request.
     * @param array $config Array of config to use.
     * @throws \Exception
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $config = $this->normalizeConfig($config);
        parent::__construct($registry, $config);
    }

    /**
     * Normalizes providers' configuration.
     *
     * @param array $config Array of config to normalize.
     * @return array
     * @throws \Exception
     */
    public function normalizeConfig(array $config)
    {
        $config = Hash::merge(Configure::read('Muffin/OAuth2'), $config);

        if (empty($config['providers'])) {
            throw new \Exception('No Oauth providers defined.');
        }

        array_walk($config['providers'], [$this, '_normalizeConfig'], $config);
        return $config;
    }

    /**
     * Callback to loop through config values.
     *
     * @param array $config Configuration.
     * @param string $key Configuration key.
     * @param array $parent Parent configuration.
     * @return void
     */
    protected function _normalizeConfig(&$config, $key, $parent)
    {
        unset($parent['providers']);

        $defaults = [
            'className' => null,
            'options' => [],
            'collaborators' => [],
        ] + $parent + $this->_defaultConfig;

        $config = array_intersect_key($config, $defaults);
        $config += $defaults;

        array_walk($config, [$this, '_validateConfig']);

        foreach (['options', 'collaborators'] as $key) {
            if (empty($parent[$key]) || empty($config[$key])) {
                continue;
            }

            $config[$key] = array_merge($parent[$key], $config[$key]);
        }
    }

    /**
     * Validates the configuration.
     *
     * @param mixed $value Value.
     * @param string $key Key.
     * @return void
     * @throws \Exception
     */
    protected function _validateConfig(&$value, $key)
    {
        if ($key === 'className' && !class_exists($value)) {
            throw new \Exception('Oauth provider does not exist');
        } elseif (in_array($key, ['options', 'collaborators']) && !is_array($value)) {
            throw new \Exception('Invalid provider settings for ' . $key);
        }
    }

    /**
     * Get a user based on information in the request.
     *
     * @param \Cake\Network\Request $request Request object.
     * @param \Cake\Network\Response $response Response object.
     * @return bool
     */
    public function authenticate(Request $request, Response $response)
    {
        return $this->getUser($request);
    }

    /**
     * Get a user based on information in the request.
     *
     * @param \Cake\Network\Request $request Request object.
     * @return mixed Either false or an array of user information
     * @throws \RuntimeException If the `Muffin/OAuth2.newUser` event is missing or returns empty.
     */
    public function getUser(Request $request)
    {
        if (!$provider = $this->provider($request)) {
            return false;
        }

        $session = $request->session();
        $sessionKey = 'oauth2state';
        $state = $request->query('state');

        if (!array_key_exists('code', $request->query)) {
            return false;
        }

        if ($this->config('options.state') && (!$state || $state !== $session->read($sessionKey))) {
            $session->delete($sessionKey);
            return false;
        }

        $token = $provider->getAccessToken('authorization_code', ['code' => $request->query('code')]);

        try {
            $data = $provider->getResourceOwner($token)->toArray();
        } catch (\Exception $e) {
            return false;
        }

        if ($this->config('userModel')) {
            $result = $this->_findUser($data[$this->config('fields.username')]);
        }

        if (empty($result)) {
            $event = $this->dispatchEvent('Muffin/OAuth2.newUser', [$provider, $data]);
            if (empty($event->result)) {
                throw new RuntimeException('
                    Missing `Muffin/OAuth2.newUser` listener which returns a local representation
                    of the user. In most cases, it is also used to create a record for the new
                    OAuth-enticated user.
                ');
            }

            $result = $event->result;
        }

        if (!$result) {
            return false;
        }

        $result += ['token' => $token->getToken()];
        return $result;
    }

    /**
     * Handles unauthenticated access attempts. Will automatically forward to the
     * requested provider's authorization URL to let the user grant access to the
     * application.
     *
     * @param \Cake\Network\Request $request Request object.
     * @param \Cake\Network\Response $response Response object.
     * @return \Cake\Network\Response|null
     */
    public function unauthenticated(Request $request, Response $response)
    {
        $provider = $this->provider($request);
        if (empty($provider) || !empty($request->query['code'])) {
            return null;
        }

        if ($this->config('options.state')) {
            $request->session()->write('oauth2state', $provider->getState());
        }

        $response->location($provider->getAuthorizationUrl());
        return $response;
    }

    /**
     * Returns the `$request`-ed provider.
     *
     * @param \Cake\Network\Request $request Current HTTP request.
     * @return \League\Oauth2\Client\Provider\GenericProvider|false
     */
    public function provider(Request $request)
    {
        if (!$alias = $request->param('provider')) {
            return false;
        }

        if (empty($this->_provider)) {
            $this->_provider = $this->_getProvider($alias);
        }

        return $this->_provider;
    }

    /**
     * Instantiates provider object.
     *
     * @param string $alias of the provider.
     * @return \League\Oauth2\Client\Provider\GenericProvider
     */
    protected function _getProvider($alias)
    {
        if (!$config = $this->config('providers.' . $alias)) {
            return false;
        }

        $this->config($config);

        if (is_object($config) && $config instanceof AbstractProvider) {
            return $config;
        }

        $class = $config['className'];
        return new $class($config['options'], $config['collaborators']);
    }
}
