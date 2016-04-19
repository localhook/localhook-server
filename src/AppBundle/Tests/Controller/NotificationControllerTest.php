<?php

namespace AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotificationControllerTest extends WebTestCase
{
    public function testHandleAction()
    {
        $client = static::createClient();

        $webHooks = ['webhook_1', 'webhook_10'];
        foreach ($webHooks as $key => $webHook) {
            $client->request(
                'POST',
                '/' . $webHook . '/notifications?get_param' . $key . '=abc&get_param' . ($key + 1) . '=def',
                [
                    'post_param' . ($key)     => 'ghi',
                    'post_param' . ($key + 1) => 'jkl',
                ]
            );
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }
}
