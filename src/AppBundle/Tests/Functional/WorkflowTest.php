<?php

namespace AppBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

class WorkflowTest extends KernelTestCase
{
    /** @var string */
    private $socketIoPort;

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
        $this->killExistingSocketIoServer();
    }

    public static function dump($var)
    {
        fwrite(STDERR, print_r($var, true));
    }

    private function killExistingSocketIoServer()
    {
        // Kill node if already started
        $command = 'lsof -n -i4TCP:' . $this->socketIoPort . ' | grep LISTEN';
        $checkNodeStarted = `$command`;
        if (strlen($checkNodeStarted) > 0) {
            $checkNodeStarted = preg_replace('/\s+/', ' ', $checkNodeStarted);
            $checkNodeStarted = explode(' ', $checkNodeStarted);
            $pid = $checkNodeStarted[1];
            `kill $pid`;
        }
    }

    public function testFullProcess()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->socketIoPort = $kernel->getContainer()->getParameter('socket_io_port');

        $this->killExistingSocketIoServer();

        $socketIoServerProcess = new Process('php app/console app:server:run-socket-io');
        $socketIoServerProcess->setTimeout(null)->start();

        $socketIoServerProcess->wait(function ($type, $buffer) use ($kernel, $socketIoServerProcess) {
            if (Process::ERR === $type) {
                self::dump('SOCKET IO ERR > ' . $buffer);
            } else {
                //self::dump($buffer);

                if (strpos($buffer, 'Channels successfully created')) {
                    $this->assertContains('Channels successfully created', $buffer);

                    $socketIoConnector = $kernel->getContainer()->get('socket_io_connector')->ensureConnection();

                    // Retrieve configuration

                    $configuration = $socketIoConnector->retrieveConfigurationFromPrivateKey(
                        '---------------------------------------1'
                    );

                    $this->assertEquals($configuration['endpoint'], 'webhook_1');

                    // Start notification watcher

                    $watchNotificationProcess = new Process(
                        'php app/console app:client:watch-notifications ' . $configuration['endpoint'] . '  --max=1'
                    );
                    $watchNotificationProcess->setTimeout(null)->start();

                    // Simulate a notification

                    $simulateNotificationProcess = new Process(
                        'php app/console app:server:simulate-notification'
                    );
                    $simulateNotificationProcess->setTimeout(null)->run();
                    $simulationOutput = $simulateNotificationProcess->getOutput();

                    $this->assertContains('Sent!', $simulationOutput);

                    while ($watchNotificationProcess->isRunning()) {
                        // waiting for process to finish
                    }
                    $watchNotificationOutput = $watchNotificationProcess->getIncrementalOutput();
                    if (strlen($watchNotificationOutput)) {
                        $this->assertContains('REQUEST: POST http://localhost:8000/notifications', $watchNotificationOutput);
                    }

                    $socketIoServerProcess->signal(SIGKILL);
                }
            }
        });
    }
}
