<?php

namespace AppBundle\Tests\Functional;

use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

class WorkflowTest extends KernelTestCase
{
    /** @var string */
    private $socketPort;

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

        $this->socketPort = static::$kernel->getContainer()->getParameter('socket_port');

        $socketServerProcess = new Process('php app/console app:run-socket');
        $socketServerProcess->setTimeout(null)->start();
        $kernel = static::$kernel;
        $socketServerProcess->wait(function ($type, $buffer) use ($kernel, $socketServerProcess) {
            if (Process::ERR === $type) {
                self::dump('SOCKET IO ERR > ' . $buffer);
            } else {
                //self::dump($buffer);

                if (strpos($buffer, 'Run socket server on port')) {
                    $this->assertContains('Run socket server on port', $buffer);

                    // Start notification watcher

                    $watchNotificationProcess = new Process(
                        'bin/localhook run webhook_1 1----------------------------------' .
                        ' http://localhost/my-project ws://127.0.0.1:1337 --no-config-file --max=1'
                    );
                    $watchNotificationProcess->setTimeout(null)->start();

                    // Simulate a notification

                    $simulateNotificationProcess = new Process(
                        'php app/console app:simulate-notification webhook_1'
                    );
                    $simulateNotificationProcess->setTimeout(null)->run();
                    $simulationOutput = $simulateNotificationProcess->getOutput();

                    $this->assertContains('Sent!', $simulationOutput);

                    while ($watchNotificationProcess->isRunning()) {
                        // waiting for process to finish
                    }
                    $watchNotificationErrorOutput = $watchNotificationProcess->getIncrementalErrorOutput();
                    $this->assertEquals(0, strlen($watchNotificationErrorOutput), $watchNotificationErrorOutput);
                    $this->assertEquals(0, $watchNotificationProcess->getExitCode());


                    $watchNotificationOutput = $watchNotificationProcess->getIncrementalOutput();
                    $this->assertContains('?get_param_1=get_value_1&get_param_2=get_value_2', $watchNotificationOutput);

                    $socketServerProcess->stop();
                }
            }
        });
    }
}
