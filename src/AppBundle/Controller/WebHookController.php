<?php

namespace AppBundle\Controller;

use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\WebHook;
use AppBundle\Form\WebHookType;
use Symfony\Component\HttpFoundation\Response;

/**
 * WebHook controller.
 *
 * @Route("/webhook")
 */
class WebHookController extends Controller
{
    /**
     * Lists all WebHook entities.
     *
     * @Route("/", name="webhook_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $webHooks = $em->getRepository('AppBundle:WebHook')->findAll();

        $deleteForms = [];
        foreach ($webHooks as $webHook) {
            $deleteForms[$webHook->getId()] = $this->createDeleteForm($webHook)->createView();
        }

        return $this->render('webhook/index.html.twig', [
            'webHooks'     => $webHooks,
            'delete_forms' => $deleteForms,
            'socket_io_client_secret' => $this->getParameter('socket_io_client_secret')
        ]);
    }

    /**
     * Creates a new WebHook entity.
     *
     * @Route("/new", name="webhook_new")
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function newAction(Request $request)
    {
        $webHook = new WebHook();
        $form = $this->createForm('AppBundle\Form\WebHookType', $webHook);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $privateKey = sha1(uniqid(rand(), true));
            $webHook->setPrivateKey($privateKey);
            $webHook->setUsername($this->getUser()->getUsername());
            $em->persist($webHook);
            $em->flush();

            // Create channel in Socket IO
            $this->get('socket_io_connector')->ensureConnection()->createChannel($webHook)->closeConnection();

            return $this->redirectToRoute('webhook_index', ['id' => $webHook->getId()]);
        }

        return $this->render('webhook/new.html.twig', [
            'webHook' => $webHook,
            'form'    => $form->createView(),
            'socket_io_client_secret' => $this->getParameter('socket_io_client_secret')
        ]);
    }

    /**
     * Deletes a WebHook entity.
     *
     * @Route("/{id}", name="webhook_delete")
     * @Method("DELETE")
     *
     * @param Request $request
     * @param WebHook $webHook
     *
     * @return RedirectResponse
     */
    public function deleteAction(Request $request, WebHook $webHook)
    {
        $form = $this->createDeleteForm($webHook);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            // Delete channel in Socket IO
            $this->get('socket_io_connector')->ensureConnection()->deleteChannel($webHook)->closeConnection();
            $em->remove($webHook);
            $em->flush();
        }

        return $this->redirectToRoute('webhook_index');
    }

    /**
     * Creates a form to delete a WebHook entity.
     *
     * @param WebHook $webHook The WebHook entity
     *
     * @return Form The form
     */
    private function createDeleteForm(WebHook $webHook)
    {
        return $this->createFormBuilder()
                    ->setAction($this->generateUrl('webhook_delete', ['id' => $webHook->getId()]))
                    ->setMethod('DELETE')
                    ->getForm();
    }
}
