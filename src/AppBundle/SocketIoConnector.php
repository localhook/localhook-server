<?php

namespace AppBundle;

use AppBundle\Entity\WebHook;
use Doctrine\ORM\EntityManager;
use Exception;
use Kasifi\Localhook\AbstractSocketIoConnector;

class SocketIoConnector extends AbstractSocketIoConnector
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string
     */
    private $socketIoServerSecret;

    public function __construct(EntityManager $em, $socketIoServerUrl, $socketIoServerSecret)
    {

        $this->em = $em;
        parent::__construct($socketIoServerUrl);
        $this->socketIoServerSecret = $socketIoServerSecret;
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
            'secret'     => $this->socketIoServerSecret,
        ]);

        return $this;
    }

    /**
     * @param WebHook $webHook
     *
     * @return $this
     * @throws Exception
     */
    public function deleteChannel(WebHook $webHook)
    {
        $this->emitAndCheck('delete_channel', [
            'endpoint'   => $webHook->getEndpoint(),
            'privateKey' => $webHook->getPrivateKey(),
            'secret'     => $this->socketIoServerSecret,
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
     * @param WebHook $webHook
     * @param array   $requestData
     *
     * @return $this
     */
    public function forwardNotification(WebHook $webHook, array $requestData)
    {
        $additionalData = [
            'secret'          => $this->socketIoServerSecret,
            'webHookEndpoint' => $webHook->getEndpoint(),
        ];
        $notificationData = array_merge($requestData, $additionalData);
        $this->client->emit('forward_notification', $notificationData);

        return $this;
    }
}
