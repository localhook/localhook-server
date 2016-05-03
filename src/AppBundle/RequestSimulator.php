<?php

namespace AppBundle;

use AppBundle\Entity\Notification;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RequestSimulator
{
    /** @var string */
    private $notificationsPrefixUrl;

    /** @var SymfonyStyle */
    private $io;

    public function __construct($notificationsPrefixUrl)
    {
        $this->notificationsPrefixUrl = $notificationsPrefixUrl;
    }

    public function setIo(SymfonyStyle $io)
    {
        $this->io = $io;
    }

    /**
     * @param $endpoint
     *
     * @return mixed|ResponseInterface
     */
    public function simulate($endpoint)
    {
        $url = $this->notificationsPrefixUrl . '/' . $endpoint . '/notifications?' . http_build_query([
                'get_param_1' => 'get_value_1',
                'get_param_2' => 'get_value_2',
            ]);
        if ($this->io) {
            $this->io->comment('URL: ' . $url);
        }
        $client = new Client();
        $headers = [];
        $body = http_build_query([
            'post_param_1' => 'post_value_1',
            'post_param_2' => 'post_value_2',
        ]);
        $request = new Request('POST', $url, $headers, $body);
        $response = $client->send($request, ['timeout' => 15]);

        return $response;
    }

    public function replay(Notification $notification)
    {
        $content = json_decode($notification->getContent(), true);
        $url = $this->notificationsPrefixUrl . '/' .
            $notification->getWebHook()->getEndpoint() .
            '/notifications?' . http_build_query($content['query']);
        if ($this->io) {
            $this->io->comment('URL: ' . $url);
        }
        $client = new Client();
        $headers = $content['headers'];
        $body = http_build_query($content['body']);
        $request = new Request($content['method'], $url, $headers, $body);
        $response = $client->send($request, ['timeout' => 15]);

        return $response;
    }
}
