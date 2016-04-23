<?php

namespace AppBundle;

use AppBundle\Entity\WebHook;
use Doctrine\ORM\EntityManager;
use Exception;
use Kasifi\Localhook\AbstractSocketIoConnector;
use Symfony\Component\HttpFoundation\Request;

class SocketIoConnector extends AbstractSocketIoConnector
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string
     */
    private $socketIoSecret;

    public function __construct(EntityManager $em, $socketIoServerUrl, $socketIoSecret)
    {

        $this->em = $em;
        parent::__construct($socketIoServerUrl);
        $this->socketIoSecret = $socketIoSecret;
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
            'secret'     => $this->socketIoSecret,
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
            'secret'     => $this->socketIoSecret,
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
     * @param Request $request
     *
     * @return $this
     */
    public function forwardNotification(WebHook $webHook, Request $request)
    {
        $this->client->emit('forward_notification', [
            'secret'          => $this->socketIoSecret,
            'webHookEndpoint' => $webHook->getEndpoint(),
            'method'          => $request->getMethod(),
            'headers'         => $request->headers->all(),
            'query'           => $request->query->all(),
            'request'         => $request->request->all(),
            //'files'           => $request->files->all(),
        ]);

        return $this;
    }
}
