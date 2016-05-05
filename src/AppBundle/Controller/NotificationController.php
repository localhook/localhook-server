<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Notification;
use AppBundle\Ratchet\AdminClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends Controller
{
    /** @var AdminClient */
    private $socketAdminClient;

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

        if (!$webHook) {
            throw new NotFoundHttpException('WebHook was not found.');
        }

        $notification = new Notification();
        $notification->setWebHook($webHook);
        $requestData = [
            'method'  => $request->getMethod(),
            'headers' => $request->headers->all(),
            'query'   => $request->query->all(),
            'body' => $request->getContent(),
            //'files'           => $request->files->all(),
        ];
        $notification->setContent(json_encode($requestData));
        $em->persist($notification);
        $em->flush();

        $this->socketAdminClient = $this->get('socket_admin_client');
        $this->socketAdminClient->start(function () use ($webHook, $requestData) {
            $this->socketAdminClient->executeSendRequest(
                $webHook, $requestData, function () {
                $this->socketAdminClient->stop();
            }, function () {
                $this->socketAdminClient->stop();
                throw new NotFoundHttpException();
            });
        });

        return new JsonResponse();
    }
}
