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
            return $this->render('Home/index.html.twig');
        }
    }
}
