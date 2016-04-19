<?php

namespace AppBundle;

use AppBundle\Entity\WebHook;
use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version1X;
use Symfony\Component\HttpFoundation\Request;

class SocketIoConnector
{
    /** @var Client */
    private $client;

    /**
     * @return $this
     */
    public function ensureConnection()
    {
        if (!$this->client) {
            $this->client = new Client(new Version1X('http://localhost:1337'));
            $this->client->initialize();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function closeConnection()
    {
        $this->client->close();

        return $this;
    }

    /**
     * @param WebHook $webHook
     *
     * @return $this
     */
    public function createChannel(WebHook $webHook)
    {
        $this->client->emit('create_channel', [
            'endpoint'   => $webHook->getEndpoint(),
            'privateKey' => $webHook->getPrivateKey(),
        ]);

        return $this;
    }

    /**
     * @param WebHook $webHook
     * @param Request $request
     *
     * @return $this
     */
    public function forwardNotification(WebHook $webHook, Request $request)
    {
        $this->client->emit('forward_notification', [
            'webHookEndpoint' => $webHook->getEndpoint(),
            'method'          => $request->getMethod(),
            'query'           => $request->query->all(),
            'request'         => $request->request->all(),
        ]);

        return $this;
    }
}
