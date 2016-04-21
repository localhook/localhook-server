<?php

namespace AppBundle\Controller;

use AppBundle\Entity\WebHook;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class NotificationController extends Controller
{
    /**
     * @Route("{endpoint}/notifications", name="notifications")
     * @param Request $request
     * @param string  $endpoint
     *
     * @return JsonResponse
     */
    public function handleAction(Request $request, $endpoint)
    {
        $em = $this->getDoctrine()->getManager();
        $webHook = $em->getRepository('AppBundle:WebHook')->findOneBy(['endpoint' => $endpoint]);

        $this->get('socket_io_connector')->ensureConnection()->forwardNotification($webHook, $request);

        return new JsonResponse();
    }
}
