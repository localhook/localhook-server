<?php

namespace AppBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

class WorkflowTest extends KernelTestCase
{
    /** @var string */
    private $socketIoPort;

    public static function dump($var)
    {
        fwrite(STDERR, print_r($var, true));
    }

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        self::bootKernel();
        // TODO Load fixtures
        $em = static::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $tool = new SchemaTool($em);
            $tool->dropSchema($metaData);
            $tool->createSchema($metaData);
            Fixtures::load(static::$kernel->getRootDir() . '/../src/AppBundle/DataFixtures/ORM/fixtures.yml', $em, ['providers' => [$this]]);
        }
    }

    public function testFullProcess()
    {

        $this->socketIoPort = static::$kernel->getContainer()->getParameter('socket_io_port');

        $socketIoServerProcess = new Process('php app/console app:server:run-socket-io');
        $socketIoServerProcess->setTimeout(null)->start();
        $kernel = static::$kernel;
        $socketIoServerProcess->wait(function ($type, $buffer) use ($kernel, $socketIoServerProcess) {
            if (Process::ERR === $type) {
                self::dump('SOCKET IO ERR > ' . $buffer);
            } else {
                //self::dump($buffer);

                if (strpos($buffer, 'Channels successfully created')) {
                    $this->assertContains('Channels successfully created', $buffer);

                    $socketIoClientConnector = $kernel->getContainer()->get('socket_io_client_connector')->ensureConnection();

                    // Retrieve configuration

                    $configuration = $socketIoClientConnector->retrieveConfigurationFromPrivateKey(
                        '1----------------------------------'
                    );

                    $this->assertEquals($configuration['endpoint'], 'webhook_1');

                    // Start notification watcher

                    $watchNotificationProcess = new Process(
                        'php app/console app:client:watch-notifications ' . $configuration['endpoint'] . '  --max=1'
                    );
                    $watchNotificationProcess->setTimeout(null)->start();

                    // Simulate a notification

                    $simulateNotificationProcess = new Process(
                        'php app/console app:server:simulate-notification webhook_1'
                    );
                    $simulateNotificationProcess->setTimeout(null)->run();
                    $simulationOutput = $simulateNotificationProcess->getOutput();

                    $this->assertContains('Sent!', $simulationOutput);

                    while ($watchNotificationProcess->isRunning()) {
                        // waiting for process to finish
                    }
                    $watchNotificationOutput = $watchNotificationProcess->getIncrementalOutput();
                    if (strlen($watchNotificationOutput)) {
                        $this->assertContains('REQUEST: POST ', $watchNotificationOutput);
                    }

                    $socketIoServerProcess->stop();
                }
            }
        });
    }
}
