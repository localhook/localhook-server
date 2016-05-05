<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeController extends Controller
{
    /**
     *
     * @Route("/", name="index")
     * @Method("GET")
     *
     * @return RedirectResponse
     */
    public function indexAction()
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('webhook_index');
        } else {
            return $this->render('home/index.html.twig');
        }
    }

    /**
     *
     * @Route("/get-started", name="get_started")
     * @Method("GET")
     *
     * @return RedirectResponse
     */
    public function getStartedAction()
    {
        if (!$this->isGranted('ROLE_USER')) {
            return $this->render('home/index.html.twig');
        }

        return $this->render('home/get-started.html.twig', [
            'socket_secret' => $this->getSocketSecret(),
        ]);
    }

    private function getSocketSecret()
    {
        $token = [$this->getParameter('socket_server_url'), $this->getUser()->getSecret()];

        return base64_encode(json_encode($token));
    }
}
