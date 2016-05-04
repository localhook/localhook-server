<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Notification;
use AppBundle\Entity\User;
use AppBundle\Entity\WebHook;
use AppBundle\Form\WebHookType;
use AppBundle\Ratchet\AdminClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WebHook controller.
 *
 * @Route("/webhook")
 */
class WebHookController extends Controller
{
    /** @var AdminClient */
    private $socketAdminClient;

    /**
     * Lists all WebHook entities.
     *
     * @Route("/", name="webhook_index")
     * @Method("GET")
     *
     * @return Response
     */
    public function indexAction()
    {
        /** @var User $user */
        if ($this->isGranted('ROLE_ADMIN')) {
            $em = $this->getDoctrine()->getManager();
            $webHooks = $em->getRepository('AppBundle:WebHook')->findAll();
        } else {
            $user = $this->getUser();
            $webHooks = $user->getWebHooks();
        }

        $deleteForms = [];
        foreach ($webHooks as $webHook) {
            $deleteForms[$webHook->getId()] = $this->createDeleteForm($webHook)->createView();
        }

        return $this->render('webhook/index.html.twig', [
            'webHooks'      => $webHooks,
            'delete_forms'  => $deleteForms,
            'socket_secret' => $this->getSocketSecret(),
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
            $webHook->setUser($this->getUser());
            $em->persist($webHook);
            $em->flush();

            // Create channel in Socket
            $this->socketAdminClient = $this->get('socket_admin_client');
            $this->socketAdminClient->start(function () use ($webHook) {
                $this->socketAdminClient->executeAddWebHook($webHook, function () {
                    $this->socketAdminClient->stop();
                }, function ($msg) {
                    $this->socketAdminClient->stop();
                    die('Socket error:' . json_encode($msg)); // fixme ratchet catch all exceptions
                });
            });

            return $this->redirectToRoute('webhook_index', ['id' => $webHook->getId()]);
        }

        return $this->render('webhook/new.html.twig', [
            'webHook'       => $webHook,
            'form'          => $form->createView(),
            'socket_secret' => $this->getSocketSecret(),
        ]);
    }

    /**
     *
     * @Route("/replay-notification/{id}", name="webhook_replay_notification")
     * @Method({"GET"})
     *
     * @param Request      $request
     * @param Notification $notification
     *
     * @return RedirectResponse|Response
     */
    public function replayNotificationAction(Request $request, Notification $notification)
    {
        $this->get('request_simulator')->replay($request, $notification);

        return $this->redirectToRoute('webhook_show', ['id' => $notification->getWebHook()->getId()]);
    }

    /**
     *
     * @Route("/{id}/simulate-notification", name="webhook_simulate_notification")
     * @Method({"GET"})
     *
     * @param WebHook $webHook
     *
     * @return RedirectResponse|Response
     */
    public function simulateNotificationAction(WebHook $webHook)
    {
        $this->get('request_simulator')->simulate($webHook->getEndpoint());

        return $this->redirectToRoute('webhook_show', ['id' => $webHook->getId()]);
    }

    /**
     *
     * @Route("/{id}/clear-notifications", name="webhook_clear_notification")
     * @Method({"GET"})
     *
     * @param WebHook $webHook
     *
     * @return RedirectResponse|Response
     * @internal param Request $request
     * @internal param Notification $notification
     *
     */
    public function clearNotificationsAction(WebHook $webHook)
    {
        $em = $this->get('doctrine.orm.default_entity_manager');
        foreach ($webHook->getNotifications() as $notification) {
            $em->remove($notification);
        }
        $em->flush();

        return $this->redirectToRoute('webhook_show', ['id' => $webHook->getId()]);
    }

    /**
     *
     * @Route("/{id}", name="webhook_show")
     * @Method("GET")
     *
     * @param WebHook $webHook
     *
     * @return Response
     */
    public function showAction(WebHook $webHook)
    {
        return $this->render('webhook/show.html.twig', [
            'webHook'       => $webHook,
            'socket_secret' => $this->getSocketSecret(),
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

            // Delete channel in Socket
            $this->socketAdminClient = $this->get('socket_admin_client');
            $this->socketAdminClient->start(function () use ($webHook, $em) {
                $this->socketAdminClient->executeRemoveWebHook(
                    $webHook,
                    function () use ($webHook, $em) {
                        $em->remove($webHook);
                        $em->flush();
                        $this->socketAdminClient->stop();
                    }, function ($msg) {
                    $this->socketAdminClient->stop();
                    die('Socket error:' . json_encode($msg)); // fixme ratchet catch all exceptions
                });
            });
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

    private function getSocketSecret()
    {
        $token = [$this->getParameter('socket_server_url'), $this->getUser()->getSecret()];

        return base64_encode(json_encode($token));
    }
}
