<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Notification;
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

        $notification = new Notification();
        $notification->setWebHook($webHook);
        $requestData = [
            'method'          => $request->getMethod(),
            'headers'         => $request->headers->all(),
            'query'           => $request->query->all(),
            'request'         => $request->request->all(),
            //'files'           => $request->files->all(),
        ];
        $notification->setContent(json_encode($requestData));
        $em->persist($notification);
        $em->flush();

        $this->get('socket_io_connector')->ensureConnection()->forwardNotification($webHook, $requestData);

        return new JsonResponse();
    }
}
