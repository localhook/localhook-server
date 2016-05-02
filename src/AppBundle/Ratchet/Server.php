<?php
namespace AppBundle\Ratchet;

use AppBundle\Entity\Client;
use AppBundle\Entity\User;
use AppBundle\Entity\WebHook;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Exception;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;
use Symfony\Component\Console\Style\SymfonyStyle;

class Server implements MessageComponentInterface
{
    /** @var SymfonyStyle */
    private $io;

    /** @var SplObjectStorage */
    protected $socketClients;

    /** @var Client[] */
    protected $clientEntities = [];

    /** @var WebHook[]|ArrayCollection */
    private $webHooks;

    /** @var EntityManager */
    private $em;

    /** @var string */
    private $socketUrl;

    /** @var string */
    private $webUrl;

    public function __construct(EntityManager $em, $webHooks, $webUrl, $socketUrl)
    {
        $this->em = $em;
        $this->webHooks = new ArrayCollection($webHooks);
        $this->socketClients = new SplObjectStorage;
        $this->purgeClients();
        $this->socketUrl = $socketUrl;
        $this->webUrl = $webUrl;
    }

    public function setIo(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->socketClients->attach($conn);
        $clientEntity = new Client();
        $this->em->persist($clientEntity);
        $this->em->flush();
        $this->clientEntities[$conn->resourceId] = $clientEntity;
        $this->io->comment("[{$conn->resourceId}] Connection");
    }

    public function onClose(ConnectionInterface $conn)
    {
        $clientEntity = $this->clientEntities[$conn->resourceId];
        $this->em->remove($clientEntity);
        $this->em->flush();
        // The connection is closed, remove it, as we can no longer send it messages
        $this->socketClients->detach($conn);
        $this->io->comment("[{$conn->resourceId}] Disconnection");
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $msg = json_decode($msg, true);
        if (isset($msg['type'])) {
            $type = $msg['type'];
            unset($msg['type']);
            $comKey = $msg['comKey'];
            unset($msg['comKey']);
            if ($type == 'retrieveConfigurationFromSecret') {
                $this->retrieveConfigurationFromSecret($from, $msg, $type, $comKey);
            } else {
                $secret = $msg['secret'];
                unset($msg['secret']);
                $user = $this->em->getRepository('AppBundle:User')->findOneBy(['secret' => $secret]);
                if ($user) {
                    switch ($type) {
                        // Server
                        case 'addWebHook':
                            $this->receiveAddWebHook($user, $from, $msg, $type, $comKey);
                            break;
                        case 'removeWebHook':
                            $this->receiveRemoveWebHook($user, $from, $msg, $type, $comKey);
                            break;
                        case 'sendRequest':
                            $this->receiveSendRequest($user, $from, $msg, $type, $comKey);
                            break;

                        // Client
                        case 'subscribeWebHook':
                            $this->subscribeWebHook($user, $from, $msg, $type, $comKey);
                            break;
                        case 'unsubscribeWebHook':
                            $this->unsubscribeWebHook($user, $from, $msg, $type, $comKey);
                            break;
                        default:
                            $this->answerError($from, $type, $comKey, 'Type "' . $type . '" not managed.');
                    }
                } else {
                    $this->answerError($from, $type, $comKey, 'Invalid secret');
                }
            }
        } else {
            $this->io->error('Missing "type" in message');
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        throw new Exception($e);
    }

    private function answerOk(ConnectionInterface $from, $type, $comKey)
    {
        $this->answer($from, $type, $comKey, []);
    }

    private function answerError(ConnectionInterface $from, $type, $comKey, $errorMessage)
    {
        $this->io->error("[{$from->resourceId}] " . $errorMessage);
        $this->answer($from, $type, $comKey, [
            'status'  => 'error',
            'message' => $errorMessage,
        ]);
    }

    private function answer(ConnectionInterface $from, $type, $comKey, array $answer)
    {
        $msg = json_encode(array_merge($answer, ['status' => 'ok', 'type' => '_' . $type, 'comKey' => $comKey]));
        $from->send($msg);
    }

    private function purgeClients()
    {
        $clientEntities = $this->em->getRepository('AppBundle:Client')->findAll();
        if (count($clientEntities)) {
            foreach ($clientEntities as $clientEntity) {
                $this->em->remove($clientEntity);
            }
            $this->em->flush();
        }
    }

    /**
     * @param User                $user
     * @param ConnectionInterface $from
     * @param string              $type
     * @param                     $comKey
     * @param string              $endpoint
     *
     * @return WebHook
     */
    private function getWebHook(User $user, ConnectionInterface $from, $type, $comKey, $endpoint)
    {
        $webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['user' => $user, 'endpoint' => $endpoint]);
        if (!$webHook) {
            $this->answerError($from, $type, $comKey, 'No webHook found for this secret');
        }

        return $webHook;
    }

    /**
     * @param $webHook
     *
     * @return bool
     */
    private function isWebHookRegistered($webHook)
    {
        $matchingWebHook = null;
        foreach ($this->webHooks as $webHookItem) {
            if ($webHook == $webHookItem) {
                $matchingWebHook = $webHookItem;
            }
        }

        return (bool)$matchingWebHook;
    }

    private function getSocketClients(WebHook $webHook)
    {
        $socketClients = [];
        $clientEntities = $webHook->getClients();
        foreach ($clientEntities as $clientEntity) {
            $clientId = array_search($clientEntity, $this->clientEntities);
            $socketClient = null;
            foreach ($this->socketClients as $clientItem) {
                if ($clientItem->resourceId == $clientId) {
                    $socketClient = $clientItem;
                    break;
                }
            }
            $socketClients[] = $socketClient;
        }

        return $socketClients;
    }

    private function receiveAddWebHook(User $user, ConnectionInterface $from, $msg, $type, $comKey)
    {
        $endpoint = $msg['endpoint'];
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $endpoint)) {
            $this->webHooks->add($webHook);
            foreach ($this->getSocketClients($webHook) as $socketClient) {
                $this->io->comment(
                    "[{$from->resourceId}] Inform {$socketClient->resourceId} for the new WebHook \"{$endpoint}\""
                );
                $type = 'forwardAddWebHook';
                $comKey = rand(100000, 999999);
                $this->answer($socketClient, 'forwardAddWebHook', $comKey, ['endpoint' => $webHook->getEndpoint()]);
            }
            $this->io->comment("[{$from->resourceId}] WebHook {$webHook->getEndpoint()} added");
            $this->answerOk($from, $type, $comKey);
        }
    }

    private function receiveRemoveWebHook(User $user, ConnectionInterface $from, $msg, $type, $comKey)
    {
        $endpoint = $msg['endpoint'];
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $endpoint)) {
            if ($this->isWebHookRegistered($webHook)) {
                foreach ($this->getSocketClients($webHook) as $socketClient) {
                    $this->io->comment(
                        "[{$from->resourceId}] Inform {$socketClient->resourceId} for the \"{$endpoint}\" WebHook deletion"
                    );
                    $type = 'forwardRemoveWebHook';
                    $comKey = rand(100000, 999999);
                    $this->answer($socketClient, 'forwardRemoveWebHook', $comKey, ['endpoint' => $webHook->getEndpoint()]);
                }
                $this->webHooks->removeElement($webHook);
                $this->answerOk($from, $type, $comKey);
                $this->io->comment("[{$from->resourceId}] WebHook {$webHook->getEndpoint()} removed");
            } else {
                $this->answerError($from, $type, $comKey, '[REMOVE] WebHook was not registered');
            }
        }
    }

    private function receiveSendRequest(User $user, ConnectionInterface $from, array $msg, $type, $comKey)
    {
        $this->io->comment("[{$from->resourceId}] Notification received");
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                // unlock external service response
                $this->answerOk($from, $type, $comKey);

                // forward request to users
                $request = $msg['request'];
                foreach ($this->getSocketClients($webHook) as $socketClient) {
                    $this->io->comment("[{$from->resourceId}] Request sent to {$socketClient->resourceId}");
                    $this->answer($socketClient, 'forwardRequest', $comKey, ['request' => $request]);
                }
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook was not registered');
            }
        }
    }

    private function retrieveConfigurationFromSecret(ConnectionInterface $from, array $msg, $type, $comKey)
    {
        $secret = $msg['secret'];
        $user = $this->em->getRepository('AppBundle:User')->findOneBy(['secret' => $secret]);
        if (!$user) {
            $this->answerError($from, $type, $comKey, 'Invalid secret');

            return;
        }
        $config = [
            'socket_url' => $this->socketUrl,
            'web_url'    => $this->webUrl,
            'secret'     => $user->getSecret(),
            'web_hooks'  => [],
        ];
        foreach ($user->getWebHooks() as $webHook) {
            $config['web_hooks'][] = ['endpoint' => $webHook->getEndpoint()];
        }
        $this->answer($from, $type, $comKey, $config);
        $this->io->comment("[{$from->resourceId}] Configuration retrieved for user {$user->getUsername()}");
    }

    private function subscribeWebHook(User $user, ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                $clientEntity = $this->clientEntities[$from->resourceId];
                $webHook->addClient($clientEntity);
                $this->em->flush();
                $this->io->comment("[{$from->resourceId}] Subscription to {$webHook->getEndpoint()}");
                $this->answerOk($from, $type, $comKey);
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook was not registered');
            }
        }
    }

    private function unsubscribeWebHook(User $user, ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                $clientEntity = $this->clientEntities[$from->resourceId];
                $webHook->removeClient($clientEntity);
                $this->em->flush();
                $this->io->comment("[{$from->resourceId}] Unsubscription to {$webHook->getEndpoint()}");
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook was not registered');
            }
        }
    }
}
