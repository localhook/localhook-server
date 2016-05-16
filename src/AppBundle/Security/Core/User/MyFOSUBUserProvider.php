<?php

namespace AppBundle\Security\Core\User;

use AppBundle\Entity\User;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseFOSUBProvider;
use Symfony\Component\Security\Core\User\UserInterface;

class MyFOSUBUserProvider extends BaseFOSUBProvider
{
    /**
     * {@inheritDoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        /** @var User $user */

        // get property from provider configuration by provider name
        // , it will return `github_id` in that case (see service definition below)
        $property = $this->getProperty($response);
        $username = $response->getUsername(); // get the unique user identifier

        //we "disconnect" previously connected users
        $existingUser = $this->userManager->findUserBy(array($property => $username));
        if (null !== $existingUser) {
            // set current user id and token to null for disconnect
            // ...

            $this->userManager->updateUser($existingUser);
        }
        //we connect current user, set current user id and token
        // ...
        $this->userManager->updateUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $userEmail = $response->getEmail();
        $user = $this->userManager->findUserByEmail($userEmail);

        // if null just create new user and set it properties
        if (null === $user) {
            $username = $response->getRealName();
            $user = new User();
            $user->setUsername($username);
            $user->setGithubAccessToken($response->getAccessToken());
            $user->setEmail($response->getEmail());
            $user->setEnabled(true);
            $password = substr(sha1(uniqid(rand(), true)), 0, 16);
            $user->setPassword($password);
            $secret = sha1(uniqid(rand(), true));
            $user->setSecret($secret);
            return $user;
        }
        // else update access token of existing user
        $serviceName = $response->getResourceOwner()->getName();
        $setter = 'set' . ucfirst($serviceName) . 'AccessToken';
        $user->$setter($response->getAccessToken());//update access token

        return $user;
    }
}
