<?php
namespace Muffin\OAuth2\Auth\Exception;

use Cake\Core\Exception\Exception;

class InvalidSettingsException extends Exception
{
    protected $message = 'Invalid provider or missing class (%s)';
    protected $code = 500;
}
