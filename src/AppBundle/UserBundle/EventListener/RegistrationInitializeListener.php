<?php

namespace AppBundle\UserBundle\EventListener;

use AppBundle\Entity\User;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Listener responsible to change the redirection at the end of the password resetting
 */
class RegistrationInitializeListener implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FOSUserEvents::REGISTRATION_INITIALIZE => 'onRegistrationInitialize',
        ];
    }

    public function onRegistrationInitialize(GetResponseUserEvent $event)
    {
        /** @var User $user */
        $user = $event->getUser();
        $secret = sha1(uniqid(rand(), true));
        $user->setSecret($secret);
    }
}
