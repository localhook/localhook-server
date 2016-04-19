<?php

namespace AppBundle\Tests\Functional;

use AppBundle\SocketIoConnector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;

class WorkflowTest extends KernelTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        // TODO Load fixtures
//        $kernel = $this->createKernel();
//        $kernel->boot();
//
//        $manager = $kernel->getContainer()->get('hautelook_alice.alice.fixtures.loader');
//        $manager->load(require($kernel->getRootDir().'/../src/AppBundle/DataFixtures/ORM/fixtures.yml'));
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown()
    {
        // TODO stop server
    }

    public static function dump($var)
    {
        fwrite(STDERR, print_r($var, true));
    }

    public function testFullProcess()
    {
        // Kill node if already started
        $checkNodeStarted = `lsof -n -i4TCP:1337 | grep LISTEN`;
        if (strlen($checkNodeStarted) > 0) {
            $checkNodeStarted = preg_replace('/\s+/', ' ', $checkNodeStarted);
            $checkNodeStarted = explode(' ', $checkNodeStarted);
            $pid = $checkNodeStarted[1];
            `kill $pid`;
        }

        $kernel = $this->createKernel();
        $kernel->boot();

        $socketIoServerProcess = new Process('php app/console app:server:run-socket-io');
        $socketIoServerProcess->setTimeout(null)->start();

        $socketIoServerProcess->wait(function ($type, $buffer) use ($kernel, $socketIoServerProcess) {
            if (Process::ERR === $type) {
                self::dump('SOCKET IO ERR > ' . $buffer);
            } else {
                self::dump($buffer);

                if (strpos($buffer, 'Channels successfully created')) {
                    $this->assertContains('Channels successfully created', $buffer);
                    $socketIoConnector = $kernel->getContainer()->get('socket_io_connector')->ensureConnection();

                    // Retrieve configuration

                    $configuration = $socketIoConnector->retrieveConfigurationFromPrivateKey(
                        '---------------------------------------1'
                    );

                    $this->assertEquals($configuration['endpoint'], 'webhook_1');

                    // Subscribe channel

                    $socketIoConnector->subscribeChannel($configuration['endpoint']);

                    // Start notification watcher

                    $watchNotificationProcess = new Process(
                        'php app/console app:client:watch-notification ' . $configuration['endpoint']
                    );
                    $watchNotificationProcess->setTimeout(null)->start();

                    // Simulate a notification

                    //

                    // get notification watcher data

                    $watchOutput = $watchNotificationProcess->getIncrementalOutput();
                    if ($watchOutput) {
                        self::dump('.............' . $watchOutput);
                    }

                    $watchNotificationProcess->wait(function ($type, $buffer) use ($kernel, $watchNotificationProcess) {
                        if (Process::ERR === $type) {
                            self::dump('WATCHER ERR > ' . $buffer);
                        } else {
                            self::dump('----------' . $buffer);
                            //$SocketIoServerProcess->stop(3, SIGINT);
                        }
                    });

                }
            }
        });
    }
}
