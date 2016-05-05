<?php

namespace AppBundle\Ratchet;

use AppBundle\Entity\WebHook;
use Exception;
use Localhook\Localhook\Ratchet\AbstractClient;
use Localhook\Localhook\Ratchet\ClientInterface;

class AdminClient extends AbstractClient implements ClientInterface
{
    public function __construct($url)
    {
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
