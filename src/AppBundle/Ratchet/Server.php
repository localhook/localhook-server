<?php
namespace AppBundle\Ratchet;

use AppBundle\Entity\Client;
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
    protected $clients;

    /** @var Client[] */
    protected $clientEntities = [];

    /** @var WebHook[]|ArrayCollection */
    private $webHooks;

    /** @var EntityManager */
    private $em;

    /**
     * @var
     */
    private $serverSecret;

    public function __construct(EntityManager $em, $webHooks, $serverSecret)
    {
        $this->em = $em;
        $this->webHooks = new ArrayCollection($webHooks);
        $this->clients = new SplObjectStorage;
        $this->serverSecret = $serverSecret;
        $this->purgeClients();
    }

    public function setIo(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
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
        $this->clients->detach($conn);
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
            switch ($type) {
                // Server
                case 'addWebHook':
                    if ($this->checkServerSecret($from, $type, $comKey, $msg)) {
                        $this->receiveAddWebHook($from, $msg, $type, $comKey);
                    }

                    break;
                case 'removeWebHook':
                    if ($this->checkServerSecret($from, $type, $comKey, $msg)) {
                        $this->receiveRemoveWebHook($from, $msg, $type, $comKey);
                    }
                    break;
                case 'sendRequest':
                    if ($this->checkServerSecret($from, $type, $comKey, $msg)) {
                        $this->receiveSendRequest($from, $msg, $type, $comKey);
                    }
                    break;

                // Client
                case 'retrieveConfigurationFromSecret':
                    $this->retrieveConfigurationFromSecret($from, $msg, $type, $comKey);
                    break;
                case 'subscribeWebHook':
                    $this->subscribeWebHook($from, $msg, $type, $comKey);
                    break;
                case 'unsubscribeWebHook':
                    $this->unsubscribeWebHook($from, $msg, $type, $comKey);
                    break;

                default:
                    $this->io->error('Type "' . $type . '" not managed.');
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

    private function checkServerSecret(ConnectionInterface $from, $type, $comKey, array $msg)
    {
        if ($this->serverSecret != $msg['serverSecret']) {
            $this->answerError($from, $type, $comKey, 'Wrong server secret');

            return false;
        }

        return true;
    }

    private function receiveAddWebHook($from, $msg, $type, $comKey)
    {
        if (isset($msg['webHookSecret'])) {
            $webHookSecret = $msg['webHookSecret'];
            $webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['privateKey' => $webHookSecret]);
            if ($webHook) {
                $this->webHooks->add($webHook);
                $this->io->comment("[{$from->resourceId}] WebHook {$webHook->getEndpoint()} added");
                $this->answerOk($from, $type, $comKey);
            } else {
                $this->answerError($from, $type, $comKey, '[ADD] No webHook found for this webHookSecret');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'No webHookSecret provided');
        }
    }

    private function receiveRemoveWebHook($from, $msg, $type, $comKey)
    {
        if (isset($msg['webHookSecret'])) {
            $webHookSecret = $msg['webHookSecret'];
            $matchingWebHook = null;
            foreach ($this->webHooks as $webHook) {
                if ($webHook->getPrivateKey() == $webHookSecret) {
                    $matchingWebHook = $webHook;
                }
            }
            //$webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['privateKey' => $webHookSecret]);
            if ($matchingWebHook) {
                /** @var WebHook $webHook */
                $webHook = $matchingWebHook;
                $this->io->comment("[{$from->resourceId}] WebHook {$webHook->getEndpoint()} removed");
                $this->webHooks->removeElement($webHook);
                $this->answerOk($from, $type, $comKey);
            } else {
                $this->answerError($from, $type, $comKey, '[REMOVE] No webHook found for this webHookSecret');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'No webHookSecret provided');
        }
    }

    private function receiveSendRequest(ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if (isset($msg['webHookSecret'])) {
            $webHookSecret = $msg['webHookSecret'];
            $request = $msg['request'];
            $webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['privateKey' => $webHookSecret]);
            if ($webHook) {
                $clientEntities = $webHook->getClients();
                foreach ($clientEntities as $clientEntity) {
                    $clientId = array_search($clientEntity, $this->clientEntities);
                    $client = null;
                    foreach ($this->clients as $clientItem) {
                        if ($clientItem->resourceId == $clientId) {
                            $client = $clientItem;
                            break;
                        }
                    }
                    $this->io->comment("[{$from->resourceId}] Request sent to {$client->resourceId}");
                    $this->answer($client, 'forwardRequest', $comKey, ['request' => $request]);
                }
                $this->answerOk($from, $type, $comKey);
            } else {
                $this->answerError($from, $type, $comKey, 'No webHookSecret found');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'No webHookSecret provided');
        }
    }

    private function retrieveConfigurationFromSecret(ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if (isset($msg['secret'])) {
            $secret = $msg['secret'];
            $webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['privateKey' => $secret]);
            if ($webHook) {
                $this->io->comment("[{$from->resourceId}] Configuration retrieved for WebHook {$webHook->getEndpoint()}");
                $this->answer($from, $type, $comKey, ['endpoint' => $webHook->getEndpoint()]);
            } else {
                $this->answerError($from, $type, $comKey, 'No webHook found for this secret');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'No secret provided when retrieving configuration from secret');
        }
    }

    private function subscribeWebHook(ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if (isset($msg['secret'])) {
            $secret = $msg['secret'];
            $webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['privateKey' => $secret]);
            if ($webHook) {
                $clientEntity = $this->clientEntities[$from->resourceId];
                $webHook->addClient($clientEntity);
                $this->em->flush();
                $this->io->comment("[{$from->resourceId}] Subscription to {$webHook->getEndpoint()}");
                $this->answer($from, $type, $comKey, ['endpoint' => $webHook->getEndpoint()]);
            } else {
                $this->answerError($from, $type, $comKey, 'No webHook found for this secret');
            }
        } else {
            $this->answerError($from, $type, $comKey, 'No secret provided when subscribing to the WebHook');
        }
    }

    private function unsubscribeWebHook(ConnectionInterface $from, array $msg, $type, $comKey)
    {
        if (isset($msg['secret'])) {
            $secret = $msg['secret'];
            $webHook = $this->em->getRepository('AppBundle:WebHook')->findOneBy(['privateKey' => $secret]);
            if ($webHook) {
                $clientEntity = $this->clientEntities[$from->resourceId];
                $webHook->removeClient($clientEntity);
                $this->em->flush();
                $this->io->comment("[{$from->resourceId}] Unsubscription to {$webHook->getEndpoint()}");
                //$this->answerOk($from, $type);
            } else {
                //$this->answerError($from, $type, 'No webHook found for this secret');
            }
        } else {
            //$this->answerError($from, $type, 'No webHookId provided');
        }
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
}
