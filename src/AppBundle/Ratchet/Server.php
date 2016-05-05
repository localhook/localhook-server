<?php
namespace AppBundle\Ratchet;

use AppBundle\Entity\Client;
use AppBundle\Entity\User;
use AppBundle\Entity\WebHook;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class Server implements MessageComponentInterface
{
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

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityManager $em,
        $webHooks,
        $webUrl,
        $socketUrl,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->webHooks = new ArrayCollection($webHooks);
        $this->socketClients = new SplObjectStorage;
        $this->purgeClients();
        $this->socketUrl = $socketUrl;
        $this->webUrl = $webUrl;
        $this->logger = $logger;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->socketClients->attach($conn);
        $clientEntity = new Client();
        $this->em->persist($clientEntity);
        $this->em->flush();
        $this->clientEntities[$conn->resourceId] = $clientEntity;
        $this->logger->info("Connection", ['conn' => $conn->resourceId]);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $clientEntity = $this->clientEntities[$conn->resourceId];
        $this->em->remove($clientEntity);
        $this->em->flush();
        // The connection is closed, remove it, as we can no longer send it messages
        $this->socketClients->detach($conn);
        $this->logger->info("Disconnection", ['conn' => $conn->resourceId]);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->logger->debug("MESSAGE RECEIVED", ['message' => $msg, 'conn' => $from->resourceId]);
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
                            $this->attacheClientToWebHook($user, $from, $msg, $type, $comKey);
                            break;
                        case 'unsubscribeWebHook':
                            $this->detachClientFromWebHook($user, $from, $msg, $type, $comKey);
                            break;
                        default:
                            $this->answerError($from, $type, $comKey, 'Type "' . $type . '" not managed.');
                    }
                } else {
                    $this->answerError($from, $type, $comKey, 'Invalid secret');
                }
            }
        } else {
            $this->logger->error('Missing "type" in message', ['message' => $msg, 'conn' => $from->resourceId]);
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
        $this->logger->error($errorMessage, ['errorMessage' => $errorMessage, 'conn' => $from->resourceId]);
        $this->answer($from, $type, $comKey, [
            'status'  => 'error',
            'message' => $errorMessage,
        ]);
    }

    private function answer(ConnectionInterface $from, $type, $comKey, array $answer)
    {
        $msg = json_encode(array_merge(['status' => 'ok', 'type' => '_' . $type, 'comKey' => $comKey], $answer));
        $this->logger->debug("MESSAGE SENT", ['message' => $msg, 'conn' => $from->resourceId]);
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

    private function receiveAddWebHook(User $user, ConnectionInterface $from, $msg, $type, $comKey)
    {
        $endpoint = $msg['endpoint'];
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $endpoint)) {
            $this->webHooks->add($webHook);
            $this->logger->info("WebHook added", [
                'endpoint' => $webHook->getEndpoint(),
                'conn'     => $from->resourceId, 'ck' => $comKey,
            ]);

            $socketClients = $this->getAttachedSocketClients($webHook);
            if (!count($socketClients)) {
                $this->logger->info("No client attached.", ['conn' => $from->resourceId, 'ck' => $comKey]);
            }
            foreach ($this->getAttachedSocketClients($webHook) as $socketClient) {
                $this->logger->info(
                    "Inform client for the new WebHook", [
                    'client'   => $socketClient->resourceId,
                    'endpoint' => $endpoint,
                    'conn'     => $from->resourceId, 'ck' => $comKey,
                ]);
                $this->answer($socketClient, 'forwardAddWebHook', rand(100000, 999999), ['endpoint' => $webHook->getEndpoint()]);
            }

            $this->answerOk($from, $type, $comKey);
        } else {
            $this->answerError($from, $type, $comKey, '[REMOVE] WebHook was not found');
        }
    }

    private function receiveRemoveWebHook(User $user, ConnectionInterface $from, $msg, $type, $comKey)
    {
        $endpoint = $msg['endpoint'];
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $endpoint)) {
            if ($this->isWebHookRegistered($webHook)) {
                foreach ($this->getAttachedSocketClients($webHook) as $socketClient) {
                    $this->logger->info(
                        "Inform client for the WebHook deletion", [
                        'client'   => $socketClient->resourceId,
                        'endpoint' => $endpoint,
                        'conn'     => $from->resourceId, 'ck' => $comKey,
                    ]);
                    $this->answer($socketClient, 'forwardRemoveWebHook', rand(100000, 999999), [
                        'endpoint' => $endpoint,
                    ]);
                }
                $this->webHooks->removeElement($webHook);
                $this->answerOk($from, $type, $comKey);
                $this->logger->info("WebHook removed", [
                    'endpoint' => $endpoint,
                    'conn'     => $from->resourceId, 'ck' => $comKey,
                ]);
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook "' . $endpoint . '" was not registered');
            }
        } else {
            $this->answerError($from, $type, $comKey, '[REMOVE] WebHook was not found');
        }
    }

    private function attacheClientToWebHook(User $user, ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                $clientEntity = $this->clientEntities[$from->resourceId];
                $webHook->addClient($clientEntity);
                $this->em->persist($webHook);
                $this->em->flush();
                $this->logger->info("Client attached to endpoint", [
                    'endpoint' => $webHook->getEndpoint(),
                    'conn'     => $from->resourceId, 'ck' => $comKey,
                ]);
                $this->answerOk($from, $type, $comKey);
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook "' . $msg['endpoint'] . '" was not registered');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'WebHook was not found');
        }
    }

    private function detachClientFromWebHook(User $user, ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                $clientEntity = $this->clientEntities[$from->resourceId];
                $webHook->removeClient($clientEntity);
                $this->em->flush();
                $this->logger->info("Client detached from webhook", [
                    'endpoint' => $webHook->getEndpoint(),
                    'conn'     => $from->resourceId, 'ck' => $comKey,
                ]);
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook "' . $msg['endpoint'] . '" was not registered');
            }
        }
    }

    private function getAttachedSocketClients(WebHook $webHook)
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

    private function receiveSendRequest(User $user, ConnectionInterface $from, array $msg, $type, $comKey)
    {
        $request = $msg['request'];
        $this->logger->info("Notification received", [
            'endpoint' => $msg['endpoint'],
            'request' => $msg['request'],
            'conn'     => $from->resourceId, 'ck' => $comKey,
        ]);
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                // unlock external service response
                $this->answerOk($from, $type, $comKey);

                // forward request to users
                $socketClients = $this->getAttachedSocketClients($webHook);
                if (count($socketClients)) {
                    foreach ($socketClients as $socketClient) {
                        $this->logger->info("Request forwarded to client", [
                            'client' => $socketClient->resourceId,
                            'conn'   => $from->resourceId, 'ck' => $comKey,
                        ]);
                        $this->answer($socketClient, 'forwardRequest', $comKey, ['request' => $request]);
                    }
                } else {
                    $this->logger->info("No client attached.", ['conn' => $from->resourceId, 'ck' => $comKey]);
                }
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook "' . $msg['endpoint'] . '" was not registered');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'No "' . $msg['endpoint'] . '" WebHook found.');
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
        $this->em->refresh($user);
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
        $this->logger->info("Configuration retrieved for user", [
            'user' => $user->getUsername(),
            'conn' => $from->resourceId, 'ck' => $comKey,
        ]);
    }
}
