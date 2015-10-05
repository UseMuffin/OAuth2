# OAuth2

[![Build Status](https://img.shields.io/travis/UseMuffin/OAuth2/master.svg?style=flat-square)](https://travis-ci.org/UseMuffin/OAuth2)
[![Coverage](https://img.shields.io/coveralls/UseMuffin/OAuth2/master.svg?style=flat-square)](https://coveralls.io/r/UseMuffin/OAuth2)
[![Total Downloads](https://img.shields.io/packagist/dt/muffin/oauth2.svg?style=flat-square)](https://packagist.org/packages/muffin/oauth2)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

[CakePHP 3][cakephp] authentication using the [league/oauth2-client][oauth2].

## Install

Using [Composer][composer]:

```
composer require muffin/oauth2:dev-master
```

You then need to load the plugin. You can use the shell command:

```
bin/cake plugin load Muffin/OAuth2
```

or by manually adding statement shown below to `bootstrap.php`:

```php
Plugin::load('Muffin/OAuth2');
```

Wait, you're not done yet. This plugin will **NOT** require any of the clients. You will have to do it yourself:

```sh
composer require "league/oauth2-github:^1.0@dev"
```

## Usage

First, start by defining the providers:

```php
// config/bootstrap.php

Configure::write('Muffin/OAuth2', [
	'providers' => [
		'github' => [
            'className' => 'League\OAuth2\Client\Provider\Github',
            // all options defined here are passed to the provider's constructor
            'options' => [
                'clientId' => 'foo',
                'clientSecret' => 'bar',
                // include a random and unique string for better security 
                // (requires ircmaxell/random-lib)
                'state' => (new RandomLib\Factory())
                    ->getLowStrengthGenerator()
                    ->generateString(mt_rand(16, 32)),
            ],
            'fields' => [
            	'username' => 'login', // maps the auth's username to github's nickname
            ],
		],
	],
]);
```

If you've worked with OAuth before, you are aware that not every time the authenticated user is
already linked to a local account (on your application). For this reason, it is possible to enable
automatic registration for select providers by adding this to the provider's configuration:

```php
// ...
Configure::write('Muffin/OAuth2', [
	'providers' => [
		'github' => [
			// ...
            'autoRegister' => true,
            // ...
		],
	],
]);
// ...
```

Anything you define at the root level of the configuration will be used as default for all
providers. For example:

```php
Configure::write('Muffin/OAuth2', [
	'providers' => [
		// ...
	],
	'autoRegister' => true
]);
```

will effectively enable auto-registration for all providers.

Next up, you need to create a route that will be used by all providers:

```php
// config/routes.php

Router::connect(
	'/oauth/:provider', 
	['controller' => 'users', 'action' => 'login'], 
	['provider' => implode('|', array_keys(Configure::read('Muffin/OAuth2.providers')))]
);
```

Now, if you have already read the book's `AuthComponent` documentation, you should be familiar with how to
add the new authentication object to it:

```php
// src/Controller/AppController.php
$this->load('Auth', [
	'authenticate' => [
		'Form',
		'Muffin/OAuth2.OAuth',
	]
]);
```

In the case you have enabled `autoRegister`, you will also need to attach a listener to the `Muffin/OAuth2.newUser`
event which subject's will contain the provider's object along with the authenticated user's profile:

```php
// src/Model/Table/UsersTable.php

use Cake\Event\Event;
use League\OAuth2\Client\Provider\AbstractProvider as Provider;

class UsersTable extends Table
{
	// ...
	public function initialize(array $config)
	{
		EventManager::instance()->on('Muffin/OAuth2.newUser', [$this, 'createUserFromOAuthProvider']);
	}

	/**
	 * @return \App\Model\Entity\User
	 */
	public function createUserFromOAuthProvider(Event $event, Provider $provider, array $data)
	{
		// ...
	}

	// ...
}
```

## Patches & Features

* Fork
* Mod, fix
* Test - this is important, so it's not unintentionally broken
* Commit - do not mess with license, todo, version, etc. (if you do change any, bump them into commits of
their own that I can ignore when I pull)
* Pull request - bonus point for topic branches

To ensure your PRs are considered for upstream, you MUST follow the [CakePHP coding standards][standards].

## Bugs & Feedback

http://github.com/usemuffin/oauth2/issues

## License

Copyright (c) 2015, [Use Muffin][muffin] and licensed under [The MIT License][mit].

[cakephp]:http://cakephp.org
[composer]:http://getcomposer.org
[mit]:http://www.opensource.org/licenses/mit-license.php
[muffin]:http://usemuffin.com
[standards]:http://book.cakephp.org/3.0/en/contributing/cakephp-coding-conventions.html
[oauth2]:https://github.com/thephpleague/oauth2-client
