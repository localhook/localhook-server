<?php

namespace AppBundle;

use AppBundle\Entity\Notification;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class RequestSimulator
{
    /** @var string */
    private $notificationsPrefixUrl;

    /** @var SymfonyStyle */
    private $io;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct($notificationsPrefixUrl, Router $router, Kernel $kernel, RequestStack $requestStack)
    {
        $this->notificationsPrefixUrl = $notificationsPrefixUrl;
        $this->router = $router;
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;
    }

    public function setIo(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    /**
     * @param string $endpoint
     *
     * @param string $method
     * @param array  $query
     * @param array  $headers
     * @param string $body
     *
     * @return mixed|ResponseInterface
     *
     */
    public function simulate(
        $endpoint,
        $method = 'POST',
        $query = [
            'get_param_1' => 'get_value_1',
            'get_param_2' => 'get_value_2',
        ],
        $headers = [
            'My-Header' => 'MyHeaderValue',
        ],
        $body = 'post_param_1=post_value_1&post_param_2=post_value_2'
    ) {

        $url = $this->notificationsPrefixUrl . '/' . $endpoint . '/notifications?' . http_build_query($query);
        if ($this->io) {
            $this->io->comment('URL: ' . $url);
        }

        $url = $this->router->generate('notifications', ['endpoint' => $endpoint]);
        $server = [];
        if ($this->requestStack && $currentRequest = $this->requestStack->getCurrentRequest()) {
            $server = $currentRequest->server->all();
        }
        $request = Request::create($url, $method, [], [], [], $server, $body);
        $request->headers->replace($headers);
        $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);

        return $response;
    }

    public function replay(Request $baseRequest, Notification $notification)
    {
        $endpoint = $notification->getWebHook()->getEndpoint();
        $content = json_decode($notification->getContent(), true);
        $url = $this->router->generate('notifications', ['endpoint' => $endpoint]);
        $request = Request::create($url, $content['method'], [], [], [], $baseRequest->server->all(), $content['body']);
        $request->headers->replace($content['headers']);
        $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST);

        return $response;
    }
}
