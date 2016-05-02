<?php

namespace AppBundle\Entity;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

/**
 *
 * @ORM\Table()
 * @ORM\Entity()
 */
class Client
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
     * @var WebHook
     *
     * @ORM\ManyToOne(targetEntity="WebHook")
     * @ORM\JoinColumn()
     */
    private $webHook;

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
     * @return WebHook
     */
    public function getWebHook()
    {
        return $this->webHook;
    }

    /**
     * @param WebHook $webHook
     */
    public function setWebHook($webHook)
    {
        $this->webHook = $webHook;
    }
}