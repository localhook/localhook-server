<?php

namespace AppBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotificationControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $webHooks = ['webhook_1', 'webhook_10'];
        foreach ($webHooks as $webHook) {
            $client->request('POST', '/' . $webHook . '/notifications?get_param1=abc&get_param2=def', [
                'post_param1' => 'ghi',
                'post_param2' => 'jkl',
            ]);
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }
}