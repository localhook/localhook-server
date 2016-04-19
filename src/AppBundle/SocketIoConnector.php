<?php

namespace AppBundle;

use AppBundle\Entity\WebHook;
use Doctrine\ORM\EntityManager;
use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version1X;
use Exception;
use Symfony\Component\HttpFoundation\Request;

class SocketIoConnector
{
    /** @var Client */
    private $client;

    /**
     * @var EntityManager
     */
    private $em;

    public function __construct(EntityManager $em)
    {

        $this->em = $em;
    }

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
     * @param array $messageKeys
     *
     * @return array
     */
    private function waitForMessage($messageKeys = [])
    {
        while (true) {
            $r = $this->client->read();
            if (!empty($r)) {
                $input = json_decode(substr($r, 2), true);
                $messageKey = $input[0];
                if (!count($messageKeys) || in_array($messageKey, array_keys($messageKeys))) {
                    return $input[1];
                }
            }
        }
    }

    private function ask($messageKey, $messageContent)
    {
        $this->client->emit($messageKey, $messageContent);

        return $this->waitForMessage(['answer_' . $messageKey]);
    }

    private function emitAndCheck($messageKey, $messageContent)
    {
        $result = $this->ask($messageKey, $messageContent);

        if ($result['status'] == 'error') {
            throw new Exception('Socket IO server failed action with error: ' . $result['message']);
        }
    }

    /**
     * @param WebHook $webHook
     *
     * @return $this
     * @throws Exception
     */
    public function createChannel(WebHook $webHook)
    {
        $this->emitAndCheck('create_channel', [
            'endpoint'   => $webHook->getEndpoint(),
            'privateKey' => $webHook->getPrivateKey(),
        ]);

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function initChannels()
    {
        $webHooks = $this->em->getRepository('AppBundle:WebHook')->findAll();
        foreach ($webHooks as $webHook) {
            $this->createChannel($webHook);
        }

        return $this;
    }

    /**
     * @param string $privateKey
     *
     * @return $this
     * @throws Exception
     */
    public function retrieveConfigurationFromPrivateKey($privateKey)
    {
        return $this->ask('retrieve_configuration_from_private_key', ['privateKey' => $privateKey]);
    }

    /**
     * @param string $channel
     *
     * @return $this
     * @throws Exception
     */
    public function subscribeChannel($channel)
    {
        $this->emitAndCheck('subscribe_channel', ['channel' => $channel]);

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

    /**
     * @return $this
     * @throws Exception
     */
    public function waitForNotification()
    {
        return $this->waitForMessage(['forwarded_notification']);
    }
}
