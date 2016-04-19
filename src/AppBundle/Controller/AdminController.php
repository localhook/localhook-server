<?php

namespace AppBundle\Controller;

use AppBundle\Entity\WebHook;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
{
    /**
     * @Route("/", name="webhook")
     * @Method({"GET"})
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $webHooks = $em->getRepository('AppBundle:WebHook')->findAll();

        return ['items' => $webHooks];
    }

    /**
     * @Route("/", name="webhook_add")
     * @Method({"POST"})
     * @Template()
     */
    public function addAction(Request $request)
    {
        return [];
    }

    /**
     * @Route("/", name="webhook_update")
     * @Method({"PUT"})
     * @Template()
     */
    public function updateAction(Request $request)
    {
        return [];
    }

    /**
     * @Route("/new", name="webhook_new")
     * @Method({"GET"})
     * @Template()
     */
    public function newAction(Request $request)
    {
        return [];
    }

    /**
     * @Route("/{id}", name="webhook_edit")
     * @Method({"GET"})
     * @Template()
     */
    public function editAction(Request $request, WebHook $webHook)
    {
        return ['item' => $webHook];
    }
}
