<?php
namespace Muffin\OAuth2\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Event\EventDispatcherTrait;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Utility\Hash;
use Exception;
use League\OAuth2\Client\Provider\AbstractProvider;
use Muffin\OAuth2\Auth\Exception\InvalidProviderException;
use Muffin\OAuth2\Auth\Exception\InvalidSettingsException;
use Muffin\OAuth2\Auth\Exception\MissingEventListenerException;
use Muffin\OAuth2\Auth\Exception\MissingProviderConfigurationException;
use RuntimeException;

class OAuthAuthenticate extends BaseAuthenticate
{

    use EventDispatcherTrait;

    /**
     * Instance of OAuth2 provider.
     *
     * @var \League\OAuth2\Client\Provider\AbstractProvider
     */
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
        $config = Hash::merge((array)Configure::read('Muffin/OAuth2'), $config);

        if (empty($config['providers'])) {
            throw new MissingProviderConfigurationException();
        }

        array_walk($config['providers'], [$this, '_normalizeConfig'], $config);

        return $config;
    }

    /**
     * Callback to loop through config values.
     *
     * @param array $config Configuration.
     * @param string $alias Provider's alias (key) in configuration.
     * @param array $parent Parent configuration.
     * @return void
     */
    protected function _normalizeConfig(&$config, $alias, $parent)
    {
        unset($parent['providers']);

        $defaults = [
                'className' => null,
                'options' => [],
                'collaborators' => [],
                'mapFields' => [],
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
     * @throws \Muffin\OAuth2\Auth\Exception\InvalidProviderException
     * @throws \Muffin\OAuth2\Auth\Exception\InvalidSettingsException
     */
    protected function _validateConfig(&$value, $key)
    {
        if ($key === 'className' && !class_exists($value)) {
            throw new InvalidProviderException([$value]);
        } elseif (!is_array($value) && in_array($key, ['options', 'collaborators'])) {
            throw new InvalidSettingsException([$key]);
        }
    }

    /**
     * Get a user based on information in the request.
     *
     * @param \Cake\Network\Request $request Request object.
     * @param \Cake\Network\Response $response Response object.
     * @return bool
     * @throws \RuntimeException If the `Muffin/OAuth2.newUser` event is missing or returns empty.
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
        if (!$rawData = $this->_authenticate($request)) {
            return false;
        }

        $user = $this->_map($rawData);

        if (!$user || !$this->config('userModel')) {
            return false;
        }

        if (!$result = $this->_touch($user)) {
            return false;
        }

        $args = [$this->_provider, $result];
        $this->dispatchEvent('Muffin/OAuth2.afterIdentify', $args);

        return $result;
    }

    /**
     * Authenticates with OAuth2 provider by getting an access token and
     * retrieving the authorized user's profile data.
     *
     * @param \Cake\Network\Request $request Request object.
     * @return array|bool
     */
    protected function _authenticate(Request $request)
    {
        if (!$this->_validate($request)) {
            return false;
        }

        $provider = $this->provider($request);
        $code = $request->query('code');

        $result = false;
        try {
            $token = $provider->getAccessToken('authorization_code', compact('code'));
            $result = compact('token') + $provider->getResourceOwner($token)->toArray();
        } catch (Exception $e) {
            // Silently catch exceptions
        }

        return $result;
    }

    /**
     * Finds or creates a local user.
     *
     * @param array $data Mapped user data.
     * @return array
     * @throws \Muffin\OAuth2\Auth\Exception\MissingEventListenerException
     */
    protected function _touch(array $data)
    {
        if ($result = $this->_findUser($data[$this->config('fields.username')])) {
            return array_merge($data, $result);
        }

        $event = 'Muffin/OAuth2.newUser';
        $args = [$this->_provider, $data];
        $event = $this->dispatchEvent($event, $args);
        $result = $event->result;
        if (empty($result)) {
            throw new MissingEventListenerException([$event]);
        }

        return $result;
    }

    /**
     * Validates OAuth2 request.
     *
     * @param \Cake\Network\Request $request Request object.
     * @return bool
     */
    protected function _validate(Request $request)
    {
        if (!array_key_exists('code', $request->query) || !$this->provider($request)) {
            return false;
        }

        $session = $request->session();
        $sessionKey = 'oauth2state';
        $state = $request->query('state');

        $result = true;
        if ($this->config('options.state') &&
            (!$state || $state !== $session->read($sessionKey))) {
            $session->delete($sessionKey);
            $result = false;
        }

        return $result;
    }

    /**
     * Maps raw provider's user profile data to local user's data schema.
     *
     * @param array $data Raw user data.
     * @return array
     */
    protected function _map($data)
    {
        if (!$map = $this->config('mapFields')) {
            return $data;
        }

        foreach ($map as $dst => $src) {
            $data[$dst] = Hash::get($data, $src);
            $data = Hash::remove($data, $src);
        }

        return $data;
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

        $response->location($provider->getAuthorizationUrl($this->_queryParams()));

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
    
    /**
     * Pass only the custom query params.
     *
     * @return array
     */
    protected function _queryParams()
    {
        $queryParams = $this->config('options');

        unset(
            $queryParams['clientId'],
            $queryParams['clientSecret'],
            $queryParams['redirectUri']
        );

        return $queryParams;
    }
}
