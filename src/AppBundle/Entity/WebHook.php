<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 *
 * @ORM\Table()
 * @ORM\Entity()
 */
class WebHook
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="guid")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="UUID")
     *
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=40)
     *
     */
    private $privateKey;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     *
     */
    private $endpoint;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     *
     */
    private $username;

    /**
     * @var Notification[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="Notification", mappedBy="webHook", cascade={"remove"}))
     *
     */
    private $notifications;

    /**
     * @var Client[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="Client", mappedBy="webHook", cascade={"remove"}))
     *
     */
    private $clients;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     *
     */
    private $createdAt;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->clients = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @param string $endpoint
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * @param string $privateKey
     */
    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;
    }

    /**
     * @return Notification[]|ArrayCollection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @param Notification $notification
     */
    public function addNotification(Notification $notification)
    {
        $this->notifications->add($notification);
        $notification->setWebHook($this);
    }

    /**
     * @return Client[]|ArrayCollection
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     * @param Client $client
     */
    public function addClient(Client $client)
    {
        $this->clients->add($client);
        $client->setWebHook($this);
    }

    /**
     * @param Client $client
     */
    public function removeClient(Client $client)
    {
        $this->clients->remove($client);
        $client->setWebHook(null);
    }
}
