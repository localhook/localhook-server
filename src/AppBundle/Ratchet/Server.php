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
use Symfony\Component\Console\Output\OutputInterface;
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

    /**
     * @param string $msg
     */
    protected function verboseLog($msg)
    {
        if ($this->io && $this->io->getVerbosity() === OutputInterface::VERBOSITY_DEBUG) {
            $this->io->comment($msg);
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->socketClients->attach($conn);
        $clientEntity = new Client();
        $this->em->persist($clientEntity);
        $this->em->flush();
        $this->clientEntities[$conn->resourceId] = $clientEntity;
        $this->io->text("[{$conn->resourceId}] Connection");
    }

    public function onClose(ConnectionInterface $conn)
    {
        $clientEntity = $this->clientEntities[$conn->resourceId];
        $this->em->remove($clientEntity);
        $this->em->flush();
        // The connection is closed, remove it, as we can no longer send it messages
        $this->socketClients->detach($conn);
        $this->io->text("[{$conn->resourceId}] Disconnection");
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $this->verboseLog("[{$from->resourceId}] <info>MESSAGE RECEIVED: {$msg}</info>");
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
        $msg = json_encode(array_merge(['status' => 'ok', 'type' => '_' . $type, 'comKey' => $comKey], $answer));
        $this->verboseLog("[{$from->resourceId}] <comment>MESSAGE SENT: {$msg}</comment>");
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
            $this->io->text("[{$from->resourceId}] WebHook {$webHook->getEndpoint()} added");

            $socketClients = $this->getAttachedSocketClients($webHook);
            if (!count($socketClients)) {
                $this->io->text("[{$from->resourceId}] No client attached.");
            }
            foreach ($this->getAttachedSocketClients($webHook) as $socketClient) {
                $this->io->text(
                    "[{$from->resourceId}] Inform {$socketClient->resourceId} for the new WebHook \"{$endpoint}\""
                );
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
                    $this->io->text(
                        "[{$from->resourceId}] Inform {$socketClient->resourceId} for the \"{$endpoint}\" WebHook deletion"
                    );
                    $this->answer($socketClient, 'forwardRemoveWebHook', rand(100000, 999999), ['endpoint' => $webHook->getEndpoint()]);
                }
                $this->webHooks->removeElement($webHook);
                $this->answerOk($from, $type, $comKey);
                $this->io->text("[{$from->resourceId}] WebHook {$webHook->getEndpoint()} removed");
            } else {
                $this->answerError($from, $type, $comKey, '[REMOVE] WebHook was not registered');
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
                $this->io->text("[{$from->resourceId}] Client attached to {$webHook->getEndpoint()}");
                $this->answerOk($from, $type, $comKey);
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook was not registered');
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
                $this->io->text("[{$from->resourceId}] Client detached from {$webHook->getEndpoint()}");
            } else {
                $this->answerError($from, $type, $comKey, 'WebHook was not registered');
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
        $this->io->text("[{$from->resourceId}] Notification received");
        if ($webHook = $this->getWebHook($user, $from, $type, $comKey, $msg['endpoint'])) {
            if ($this->isWebHookRegistered($webHook)) {
                // unlock external service response
                $this->answerOk($from, $type, $comKey);

                // forward request to users
                $request = $msg['request'];
                foreach ($this->getAttachedSocketClients($webHook) as $socketClient) {
                    $this->io->text("[{$from->resourceId}] Request sent to {$socketClient->resourceId}");
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
        $this->em->refresh($user);
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
        $this->io->text("[{$from->resourceId}] Configuration retrieved for user {$user->getUsername()}");
    }
}
