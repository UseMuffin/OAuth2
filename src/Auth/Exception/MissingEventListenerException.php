<?php
namespace Muffin\OAuth2\Auth\Exception;

use Cake\Core\Exception\Exception;

class MissingEventListenerException extends Exception
{
    protected $message = 'Missing listener to the `%s` event.';
}
