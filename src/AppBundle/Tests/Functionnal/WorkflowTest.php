<?php

namespace AppBundle\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Process\Process;

class WorkflowTest extends WebTestCase
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
        $this->socketPort = static::$kernel->getContainer()->getParameter('socket_port');
        $em = static::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $metaData = $em->getMetadataFactory()->getAllMetadata();
        if (!empty($metaData)) {
            $tool = new SchemaTool($em);
            $tool->dropSchema($metaData);
            $tool->createSchema($metaData);
            Fixtures::load(static::$kernel->getRootDir() . '/../src/AppBundle/DataFixtures/ORM/fixtures.yml', $em, ['providers' => [$this]]);
        }
    }

    public function testCompleteScenario()
    {
        $socketServerProcess = new Process('php app/console app:run-socket -vvv -e test');
        $socketServerProcess->setTimeout(null)->start();
        $socketServerProcess->wait(function ($type, $buffer) use ($socketServerProcess) {
            if (Process::ERR === $type) {
                self::dump('SOCKET SERVER OUT > ' . $buffer);
                if (strpos($buffer, 'Run socket server on port')) {

                    $client = static::createClient();
                    $client->followRedirects();

                    $crawler = $client->request('GET', '/');

                    $this->assertGreaterThan(0, $crawler->filter('html:contains("Welcome to Localhook!")')->count());

                    $link = $form = $crawler->selectLink('Quick sign up!')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertGreaterThan(
                        0,
                        $crawler->filter('html:contains("Quick register to Localhook!")')->count()
                    );

                    $form = $crawler->selectButton('Register')->form();
                    $form['fos_user_registration_form[email]'] = 'test@example.com';
                    $form['fos_user_registration_form[username]'] = 'test';
                    $form['fos_user_registration_form[plainPassword][first]'] = 'test';
                    $form['fos_user_registration_form[plainPassword][second]'] = 'test';
                    $crawler = $client->submit($form);
                    $this->assertGreaterThan(0, $crawler->filter('html:contains("The user has been created successfully")')
                                                        ->count());

                    $link = $form = $crawler->selectLink('Go to dashboard')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertGreaterThan(0, $crawler->filter('html:contains("List of your forwards")')->count());

                    $link = $form = $crawler->selectLink('Get started')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertGreaterThan(0, $crawler->filter('html:contains("How does it work")')->count());

                    $link = $form = $crawler->selectLink('Create a forward')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertGreaterThan(0, $crawler->filter('html:contains("Create a new forward")')->count());

                    $form = $crawler->selectButton('Submit')->form();
                    $form['web_hook[endpoint]'] = 'test';
                    $crawler = $client->submit($form);
                    $this->assertGreaterThan(0, $crawler->filter('html:contains("What a fresh URL")')->count());

                    // Start notification watcher
//                    $watchNotificationProcess = new Process(
//                        'bin/localhook delete-configuration; bin/localhook run test ' .
//                        '"http://127.0.0.1/" --secret="' . $secret . '" --max=1 -vvv'
//                    );
//                    $watchNotificationProcess->setTimeout(30)->start();

                    $link = $form = $crawler->selectLink('Simulate notification')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertEquals(1, $crawler->filter('table#notifications>tbody>tr')->count());
//
//                    while ($watchNotificationProcess->isRunning()) {
//                        self::dump($watchNotificationProcess->getIncrementalOutput());
//                        self::dump($watchNotificationProcess->getIncrementalErrorOutput());
//                        // waiting for process to finish
//                    }
//
//                    //$this->assertEquals(0, strlen($watchNotificationErrorOutput), $watchNotificationErrorOutput);
//                    //$this->assertEquals(0, $watchNotificationProcess->getExitCode());
//                    //$this->assertContains('Max forward reached', $watchNotificationOutput);
//
                    $link = $form = $crawler->selectLink('Replay')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertEquals(2, $crawler->filter('table#notifications>tbody>tr')->count());

                    $form = $crawler->selectButton('Clear notifications')->form();
                    $crawler = $client->submit($form);
                    $this->assertGreaterThan(0, $crawler->filter('html:contains("What a fresh URL!")')->count());
//
                    $link = $form = $crawler->selectLink('Back to the list of all forwards')->link();
                    $this->assertNotNull($link);
                    $crawler = $client->click($link);
                    $this->assertEquals(1, $crawler->filter('html:contains("List of your forwards")')->count());

//                    $form = $crawler->selectButton('Delete test')->form();
//                    $crawler = $client->submit($form);
//                    self::dump($crawler->text());die;
                    //$this->assertEquals(1, $crawler->filter('html:contains("Your endpoints list is empty")')->count());

//                    $this->assertEquals('', $socketServerProcess->getIncrementalErrorOutput());
                    $socketServerProcess->stop();
                }
            }
        });
    }
}
