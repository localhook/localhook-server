<?php

namespace AppBundle;

use AppBundle\Entity\WebHook;
use Doctrine\ORM\EntityManager;
use Exception;
use Localhook\Core\AbstractSocketIoConnector;
use Symfony\Component\HttpFoundation\Request;

class SocketIoConnector extends AbstractSocketIoConnector
{
    /**
     * @var EntityManager
     */
    private $em;

    public function __construct(EntityManager $em, $socketIoPort)
    {

        $this->em = $em;
        parent::__construct($socketIoPort);
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
