<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Notification;
use AppBundle\Entity\WebHook;
use AppBundle\Ratchet\AdminClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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
     * @Method({"GET", "POST", "PUT"})
     * @param Request $request
     * @param string  $endpoint
     *
     * @return JsonResponse
     */
    public function handleAction(Request $request, $endpoint)
    {
        $em = $this->get('doctrine.orm.default_entity_manager');
        $webHook = $em->getRepository('AppBundle:WebHook')->findOneBy(['endpoint' => $endpoint]);

        if (!$webHook) {
            throw new NotFoundHttpException('WebHook was not found.');
        }

        $requestData = $this->handleNotification($request, $webHook);

        $this->forwardNotification($webHook, $requestData);

        return new JsonResponse();
    }

    /**
     * @param Request $request
     * @param         $webHook
     *
     * @return array
     */
    private function handleNotification(Request $request, $webHook)
    {
        $em = $this->get('doctrine.orm.default_entity_manager');

        $notification = new Notification();
        $notification->setWebHook($webHook);
        $requestData = [
            'method'  => $request->getMethod(),
            'headers' => $request->headers->all(),
            'query'   => $request->query->all(),
            'body'    => $request->getContent(),
            //'files'           => $request->files->all(),
        ];
        $notification->setContent(json_encode($requestData));
        $em->persist($notification);
        $em->flush();

        return $requestData;
    }

    /**
     * @param $webHook
     * @param $requestData
     */
    private function forwardNotification(WebHook $webHook, $requestData)
    {
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
    }
}
