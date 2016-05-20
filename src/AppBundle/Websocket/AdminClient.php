<?php

namespace AppBundle\Websocket;

use AppBundle\Entity\WebHook;
use Exception;
use Localhook\Localhook\Websocket\AbstractClient;

class AdminClient extends AbstractClient
{
    public function routeInputEvents($type, $msg, $comKey)
    {
        switch ($type) {
            case '_addWebHook':
            case '_removeWebHook':
            case '_sendRequest':
                return $this->defaultReceive($msg, $comKey);
            default:
                throw new Exception('Type "' . $type . '" not managed');
        }
    }

    public function executeSendRequest(WebHook $webHook, array $requestData, callable $onSuccess, callable $onError)
    {
        $this->defaultExecute('sendRequest', [
            'endpoint' => $webHook->getEndpoint(),
            'secret'   => $webHook->getUser()->getSecret(),
            'request'  => $requestData,
        ], $onSuccess, $onError);
    }

    public function executeAddWebHook(WebHook $webHook, callable $onSuccess, callable $onError)
    {
        $this->defaultExecute('addWebHook', [
            'endpoint' => $webHook->getEndpoint(),
            'secret'   => $webHook->getUser()->getSecret(),
        ], $onSuccess, $onError);
    }

    public function executeRemoveWebHook(WebHook $webHook, callable $onSuccess, callable $onError)
    {
        $this->defaultExecute('removeWebHook', [
            'endpoint' => $webHook->getEndpoint(),
            'secret'   => $webHook->getUser()->getSecret(),
        ], $onSuccess, $onError);
    }
}
