<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;

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

    /**
     * @ORM\Column(name="github_id", type="string", length=255, nullable=true)
     */
    private $githubId;

    private $githubAccessToken;

    public function __construct()
    {
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

    public function getSalt()
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function getGithubId()
    {
        return $this->githubId;
    }

    /**
     * @param mixed $githubId
     */
    public function setGithubId($githubId)
    {
        $this->githubId = $githubId;
    }

    /**
     * @return mixed
     */
    public function getGithubAccessToken()
    {
        return $this->githubAccessToken;
    }

    /**
     * @param mixed $githubAccessToken
     */
    public function setGithubAccessToken($githubAccessToken)
    {
        $this->githubAccessToken = $githubAccessToken;
    }
}
