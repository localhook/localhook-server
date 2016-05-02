<?php

namespace AppBundle\Tests\Controller;

use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Fixtures;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Process\Process;

class WebHookControllerTest extends WebTestCase
{
    /** @var string */
    private $socketPort;

    public static function dump($var)
    {
        fwrite(STDERR, print_r($var, true));
    }

    /** @var Client */
    private $client = null;

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

    private function logIn()
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $session = $container->get('session');
        $userManager = $container->get('fos_user.user_manager');
        $loginManager = $container->get('fos_user.security.login_manager');
        $firewallName = $container->getParameter('fos_user.firewall_name');

        $user = $userManager->findUserBy(['username' => 'admin']);
        $loginManager->loginUser($firewallName, $user);

        // save the login token into the session and put it in a cookie
        $container->get('session')->set('_security_' . $firewallName,
            serialize($container->get('security.token_storage')->getToken()));
        $container->get('session')->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
        $this->client = $client;
    }

    public function testCompleteScenario()
    {
        $this->socketPort = static::$kernel->getContainer()->getParameter('socket_port');

        $socketServerProcess = new Process('php app/console app:run-socket');
        $socketServerProcess->setTimeout(null)->start();
        $kernel = static::$kernel;
        $socketServerProcess->wait(function ($type, $buffer) use ($kernel, $socketServerProcess) {
            if (Process::ERR === $type) {
                self::dump('SOCKET ERR > ' . $buffer);
            } else {
                //self::dump($buffer);

                if (strpos($buffer, 'Run socket server on port')) {

                    $this->logIn();

                    // Create a new entry in the database
                    $crawler = $this->client->request('GET', '/webhook/');
                    dump($this->client->getResponse()->headers->all());
                    die;
                    $this->assertEquals(200, $this->client->getResponse()
                                                          ->getStatusCode(), "Unexpected HTTP status code for GET /webhook/");
                    $crawler = $this->client->click($crawler->selectLink('Create a new webhook')->link());

                    // Fill in the form and submit it
                    $form = $crawler->selectButton('Submit')->form([
                        'web_hook[endpoint]' => 'webhook_test',
                    ]);

                    $this->client->submit($form);
                    $crawler = $this->client->followRedirect();

                    // Check data in the show view
                    $this->assertGreaterThan(0, $crawler->filter('a:contains("webhook_test")')
                                                        ->count(), 'Missing element a:contains("webhook_test")');
                    // Delete the entity
                    $this->client->submit($crawler->selectButton('Delete webhook_test')->form());
                    $this->client->followRedirect();

                    // Check the entity has been delete on the list
                    $this->assertNotRegExp('/webhook_test/', $this->client->getResponse()->getContent());

                    $socketServerProcess->stop();
                }
            }
        });
    }
}
