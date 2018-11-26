# OAuth2

[![Build Status](https://img.shields.io/travis/UseMuffin/OAuth2/master.svg?style=flat-square)](https://travis-ci.org/UseMuffin/OAuth2)
[![Coverage](https://img.shields.io/coveralls/UseMuffin/OAuth2/master.svg?style=flat-square)](https://coveralls.io/r/UseMuffin/OAuth2)
[![Total Downloads](https://img.shields.io/packagist/dt/muffin/oauth2.svg?style=flat-square)](https://packagist.org/packages/muffin/oauth2)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

[CakePHP 3][cakephp] authentication using the [league/oauth2-client][oauth2].

## Install

Using [Composer][composer]:

```
composer require muffin/oauth2
```

You then need to load the plugin.

```
bin/cake plugin load Muffin/OAuth2
```

Wait, you're not done yet. This plugin will **NOT** require any of the clients.
You will have to do it yourself. For e.g. if you want to use Github client do:

```sh
composer require "league/oauth2-github"
```

## Usage

First, start by defining the providers:

```php
// either in `config/bootstrap.php`
Configure::write('Muffin/OAuth2', [
    'providers' => [
        'github' => [
            'className' => 'League\OAuth2\Client\Provider\Github',
            // all options defined here are passed to the provider's constructor
            'options' => [
                'clientId' => 'foo',
                'clientSecret' => 'bar',
            ],
            'mapFields' => [
                'username' => 'login', // maps the app's username to github's login
            ],
            // ... add here the usual AuthComponent configuration if needed like fields, etc.
        ],
    ],
]);

// or in `src/Controller/AppController.php`
$this->loadComponent('Auth', [
    'authenticate' => [
        // ...
        'Muffin/OAuth2.OAuth' => [
            'providers' => [
                // the array from example above
            ],
        ],
    ],
]);
```

Upon successful authorization, and if the user has no local instance, an event (`Muffin/OAuth2.newUser`)
is triggered. Use it to create a user like so:

```php
// bootstrap.php
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
EventManager::instance()->on(
    'Muffin/OAuth2.newUser',
    [TableRegistry::get('Users'), 'createNewUser']
);

// UsersTable.php
use Cake\Event\Event;
use League\OAuth2\Client\Provider\AbstractProvider;
public function createNewUser(Event $event, AbstractProvider $provider, array $data)
{
    $entity = $this->newEntity($data);
    $this->save($entity);

    return $entity->toArray(); // user data to be used in session
}
```

Finally, once token is received, the `Muffin/OAuth2.afterIdentify` event is triggered. Use this to update your local
tokens for example:

```php
// bootstrap.php
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
EventManager::instance()->on(
    'Muffin/OAuth2.afterIdentify',
    [TableRegistry::get('Tokens'), 'createOrUpdate']
);

// TokensTable.php
use Cake\Event\Event;
use League\OAuth2\Client\Provider\AbstractProvider;

public function createOrUpdate(Event $event, AbstractProvider $provider, array $data)
{
    // ...
    return; // void
}
```

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
$this->loadComponent('Auth', [
    'authenticate' => [
        'Form',
        'Muffin/OAuth2.OAuth',
    ]
]);
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

Copyright (c) 2018, [Use Muffin][muffin] and licensed under [The MIT License][mit].

[cakephp]:http://cakephp.org
[composer]:http://getcomposer.org
[mit]:http://www.opensource.org/licenses/mit-license.php
[muffin]:http://usemuffin.com
[standards]:http://book.cakephp.org/3.0/en/contributing/cakephp-coding-conventions.html
[oauth2]:https://github.com/thephpleague/oauth2-client
