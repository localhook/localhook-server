<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="fos_user")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var WebHook[]|ArrayCollection
     * @ORM\OneToMany(targetEntity="WebHook", mappedBy="user", cascade={"remove"}))
     *
     */
    private $webHooks;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=40)
     */
    private $secret;

    public function __construct() {
        parent::__construct();
        $this->webHooks = new ArrayCollection();
    }

    /**
     * @return WebHook[]|ArrayCollection
     */
    public function getWebHooks()
    {
        return $this->webHooks;
    }

    /**
     * @param WebHook $webHook
     */
    public function addWebHook(WebHook $webHook)
    {
        $this->webHooks->add($webHook);
        $webHook->setUser($this);
    }

    /**
     * @param WebHook $webHook
     */
    public function removeWebHook(WebHook $webHook)
    {
        $this->webHooks->remove($webHook);
        $webHook->setUser(null);
    }

    /**
     * @param string $secret
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }
}
