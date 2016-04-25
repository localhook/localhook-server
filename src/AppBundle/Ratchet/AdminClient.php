<?php

namespace AppBundle\Ratchet;

use Exception;
use Localhook\Localhook\Ratchet\AbstractClient;
use Localhook\Localhook\Ratchet\ClientInterface;

class AdminClient extends AbstractClient implements ClientInterface
{
    private $serverSecret;

    public function __construct($url, $serverSecret)
    {
        $this->serverSecret = $serverSecret;
        $this->defaultFields = ['serverSecret' => $this->serverSecret];
        parent::__construct($url);
    }

    public function routeInputEvents($type, $msg, $comKey)
    {
        switch ($type) {
            case '_sendRequest':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_addWebHook':
                $this->defaultReceive($msg, $comKey);
                break;
            case '_removeWebHook':
                $this->defaultReceive($msg, $comKey);
                break;
            default:
                throw new Exception('Type "' . $type . '" not managed');
        }
    }

    public function executeSendRequest($webHookSecret, array $requestData, callable $onSuccess)
    {
        $this->defaultExecute('sendRequest', [
            'webHookSecret' => $webHookSecret,
            'request' => $requestData,
        ], $onSuccess);
    }

    public function executeAddWebHook($webHookSecret, callable $onSuccess)
    {
        $this->defaultExecute('addWebHook', [
            'webHookSecret' => $webHookSecret,
        ], $onSuccess);
    }

    public function executeRemoveWebHook($webHookSecret, callable $onSuccess)
    {
        $this->defaultExecute('removeWebHook', [
            'webHookSecret' => $webHookSecret,
        ], $onSuccess);
    }
}
